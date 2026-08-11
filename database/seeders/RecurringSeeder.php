<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\LabelType;
use App\Enums\RecurringFrequency;
use App\Enums\RecurringType;
use App\Models\FamilyMember;
use App\Models\Label;
use App\Models\Recurring;
use App\Services\RecurringOccurrenceGenerator;
use Illuminate\Database\Seeder;

class RecurringSeeder extends Seeder
{
    public function run(): void
    {
        $utilities = $this->labelId('utilities-bills');
        $subscriptions = $this->labelId('subscriptions-memberships');
        $investments = $this->labelId('investments');

        $alongId = FamilyMember::query()
            ->where(function ($query): void {
                $query->where('display_name', 'Along')
                    ->orWhere('name', 'like', 'Nor Ezrieana%');
            })
            ->value('id');

        $templates = [
            [
                'title' => 'GPROP Monthly Bills',
                'type' => RecurringType::VariableBill,
                'label_id' => $utilities,
                'expected_amount' => 199.14,
                'merchant_aliases' => ['GPROP'],
                'anchor_day' => 5,
            ],
            [
                'title' => 'Cursor',
                'type' => RecurringType::Subscription,
                'label_id' => $subscriptions,
                'expected_amount' => 84.79,
                'merchant_aliases' => ['Cursor', 'Anysphere'],
                'anchor_day' => 8,
            ],
            [
                'title' => 'TIME Internet',
                'type' => RecurringType::FixedBill,
                'label_id' => $utilities,
                'expected_amount' => 102.80,
                'merchant_aliases' => ['TIME', 'TIME dotCom'],
                'anchor_day' => 17,
            ],
            [
                'title' => 'TNB Electricity',
                'type' => RecurringType::VariableBill,
                'label_id' => $utilities,
                'expected_amount' => 70.00,
                'merchant_aliases' => ['Tenaga', 'myTNB', 'TNB'],
                'anchor_day' => 5,
            ],
            [
                'title' => 'PTPTN',
                'type' => RecurringType::DebtInstalment,
                'label_id' => $utilities,
                'expected_amount' => 50.00,
                'merchant_aliases' => ['PTPTN'],
                'anchor_day' => 5,
            ],
            [
                'title' => 'ASNB Investment',
                'type' => RecurringType::TransferInvestment,
                'label_id' => $investments,
                'expected_amount' => 200.00,
                'merchant_aliases' => ['ASNB', 'Amanah Saham'],
                'anchor_day' => 5,
            ],
            [
                'title' => 'Tabung Raya 2027',
                'type' => RecurringType::TransferInvestment,
                'label_id' => $investments,
                'expected_amount' => 50.00,
                'goal_target_amount' => 600.00,
                'instalment_total' => 12,
                'instalment_remaining' => 12,
                'merchant_aliases' => ['Maybank', 'Tabung'],
                'anchor_day' => 5,
            ],
            [
                'title' => 'Shopee PayLater',
                'type' => RecurringType::DebtInstalment,
                'label_id' => $this->labelId('groceries-household'),
                'expected_amount' => 180.16,
                'instalment_total' => 3,
                'instalment_remaining' => 3,
                'merchant_aliases' => ['Shopee PayLater', 'Shopee'],
                'anchor_day' => 5,
            ],
        ];

        if ($alongId !== null) {
            $templates = array_merge($templates, [
                [
                    'title' => 'Home Financing-i',
                    'type' => RecurringType::DebtInstalment,
                    'label_id' => $utilities,
                    'family_member_id' => $alongId,
                    'expected_amount' => 1327.00,
                    'merchant_aliases' => ['Commodity Murabahah', 'Home Financing'],
                    'anchor_day' => 24,
                ],
                [
                    'title' => 'Celcom Mobile',
                    'type' => RecurringType::VariableBill,
                    'label_id' => $utilities,
                    'family_member_id' => $alongId,
                    'expected_amount' => 338.55,
                    'merchant_aliases' => ['CELCOM', 'CelcomDigi'],
                    'anchor_day' => 24,
                ],
                [
                    'title' => 'Indah Water',
                    'type' => RecurringType::VariableBill,
                    'label_id' => $utilities,
                    'family_member_id' => $alongId,
                    'expected_amount' => 105.00,
                    'merchant_aliases' => ['Indah Water', 'ATX'],
                    'anchor_day' => 24,
                ],
                [
                    'title' => 'Cukai Taksiran',
                    'type' => RecurringType::FixedBill,
                    'label_id' => $utilities,
                    'family_member_id' => $alongId,
                    'expected_amount' => 222.30,
                    'interval_months' => 12,
                    'merchant_aliases' => ['MyMPS', 'Cukai Taksiran'],
                    'anchor_day' => 24,
                ],
            ]);
        }

        $generator = app(RecurringOccurrenceGenerator::class);

        foreach ($templates as $template) {
            $recurring = Recurring::query()->updateOrCreate(
                ['title' => $template['title']],
                [
                    'type' => $template['type'],
                    'label_id' => $template['label_id'] ?? null,
                    'family_member_id' => $template['family_member_id'] ?? null,
                    'is_shared' => false,
                    'expected_amount' => $template['expected_amount'] ?? null,
                    'goal_target_amount' => $template['goal_target_amount'] ?? null,
                    'frequency' => RecurringFrequency::Repeating,
                    'interval_months' => $template['interval_months'] ?? 1,
                    'anchor_day' => $template['anchor_day'] ?? null,
                    'starts_on' => now()->startOfMonth()->toDateString(),
                    'next_due_on' => null,
                    'instalment_total' => $template['instalment_total'] ?? null,
                    'instalment_remaining' => $template['instalment_remaining'] ?? null,
                    'merchant_aliases' => $template['merchant_aliases'] ?? [],
                    'notify_filament' => true,
                    'notify_whatsapp' => true,
                    'is_active' => true,
                ],
            );

            if ($recurring->next_due_on === null) {
                $recurring->next_due_on = $recurring->resolveInitialDueOn();
                $recurring->save();
            }

            $generator->generateFor($recurring->fresh());
        }
    }

    private function labelId(string $slug): ?int
    {
        return Label::query()
            ->where('type', LabelType::Finance)
            ->where('slug', $slug)
            ->value('id');
    }
}
