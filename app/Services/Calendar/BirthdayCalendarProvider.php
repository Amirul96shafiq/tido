<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Resources\FamilyMembers\FamilyMemberResource;
use App\Models\FamilyMember;
use App\Models\User;
use App\Support\Calendar\CalendarEvent;
use App\Support\Calendar\CalendarEventProvider;
use App\Support\Calendar\CalendarModule;
use App\Support\HouseholdAccess;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class BirthdayCalendarProvider implements CalendarEventProvider
{
    public function module(): CalendarModule
    {
        return CalendarModule::Household;
    }

    public function filterKey(): string
    {
        return 'birthdays';
    }

    public function filterLabel(): string
    {
        return 'Birthdays';
    }

    /**
     * @return Collection<int, CalendarEvent>
     */
    public function eventsForRange(CarbonInterface $start, CarbonInterface $end, User $viewer): Collection
    {
        $rangeStart = Carbon::parse($start)->startOfDay();
        $rangeEnd = Carbon::parse($end)->endOfDay();
        $year = (int) $rangeStart->year;

        $events = collect();

        User::query()
            ->whereNotNull('date_of_birth')
            ->orderBy('id')
            ->each(function (User $user) use ($rangeStart, $rangeEnd, $year, $viewer, $events): void {
                $event = $this->eventFromBirthDate(
                    name: filled($user->display_name) ? (string) $user->display_name : $user->name,
                    dateOfBirth: $user->date_of_birth,
                    year: $year,
                    rangeStart: $rangeStart,
                    rangeEnd: $rangeEnd,
                    isCurrentViewer: $user->id === $viewer->id,
                    url: $user->id === $viewer->id ? EditProfile::getUrl() : null,
                    meta: ['user_id' => $user->id],
                );

                if ($event !== null) {
                    $events->push($event);
                }
            });

        FamilyMember::query()
            ->whereNotNull('date_of_birth')
            ->orderBy('id')
            ->each(function (FamilyMember $member) use ($rangeStart, $rangeEnd, $year, $viewer, $events): void {
                $event = $this->eventFromBirthDate(
                    name: filled($member->display_name) ? (string) $member->display_name : $member->name,
                    dateOfBirth: $member->date_of_birth,
                    year: $year,
                    rangeStart: $rangeStart,
                    rangeEnd: $rangeEnd,
                    isCurrentViewer: $viewer->family_member_id !== null
                        && (int) $viewer->family_member_id === (int) $member->id,
                    url: $this->familyMemberEditUrl($member, $viewer),
                    meta: ['family_member_id' => $member->id],
                );

                if ($event !== null) {
                    $events->push($event);
                }
            });

        return $events->sortBy([
            ['date', 'asc'],
            ['title', 'asc'],
        ])->values();
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function eventFromBirthDate(
        string $name,
        ?CarbonInterface $dateOfBirth,
        int $year,
        Carbon $rangeStart,
        Carbon $rangeEnd,
        bool $isCurrentViewer,
        ?string $url,
        array $meta,
    ): ?CalendarEvent {
        if ($dateOfBirth === null) {
            return null;
        }

        $birthday = Carbon::parse($dateOfBirth)->copy()->year($year)->startOfDay();

        if ($birthday->lt($rangeStart) || $birthday->gt($rangeEnd)) {
            return null;
        }

        $age = $year - (int) Carbon::parse($dateOfBirth)->year;

        return new CalendarEvent(
            module: CalendarModule::Household,
            type: 'birthday',
            date: $birthday,
            title: $name,
            subtitle: 'Turns '.$age,
            status: $isCurrentViewer ? 'Your birthday' : null,
            colorKey: $isCurrentViewer ? 'birthday-self' : 'birthday',
            url: $url,
            meta: array_merge($meta, [
                'age' => $age,
                'is_current_viewer' => $isCurrentViewer,
            ]),
        );
    }

    private function familyMemberEditUrl(FamilyMember $member, User $viewer): ?string
    {
        if (! HouseholdAccess::isPrimary()) {
            return null;
        }

        return FamilyMemberResource::getUrl('edit', ['record' => $member]);
    }
}
