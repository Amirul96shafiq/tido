<?php

declare(strict_types=1);

namespace App\Prompts;

use App\Models\Label;

class ManualInvoiceLabelPrompt
{
    /**
     * @param  list<string>  $descriptions
     */
    public static function build(array $descriptions): string
    {
        $labelLines = Label::financeLabels()
            ->map(function (Label $label): string {
                $hintText = self::plainTextHint($label->description);
                $hint = $hintText !== ''
                    ? ' — '.$hintText
                    : '';

                return '- '.$label->name.$hint;
            })
            ->implode("\n");

        $itemLines = collect($descriptions)
            ->values()
            ->map(fn (string $description, int $index): string => ($index + 1).'. '.$description)
            ->implode("\n");

        return <<<PROMPT
You classify expense line items into finance labels for a Malaysian spending tracker.
You must respond with a raw JSON object only. Do not wrap it in markdown formatting (like ```json).

Label rules (follow strictly):
- Every item in items[] MUST include a label and the exact original description.
- Classify each line by its description. Use the exact label name from the list below.
- Pick the closest match when ambiguous.
- Ready-to-eat / convenience-store snacks and drinks → Food & Dining. Supermarket pantry, fresh produce, and household consumables → Groceries & Household.
- Packaged bread loaves (e.g. Gardenia Original Classic Bread) → Groceries & Household. Gardenia Quick Bites / Puazz and similar ready-to-eat snacks → Food & Dining.

Available labels (use exact name in each item's "label" field):
{$labelLines}

Line items to classify:
{$itemLines}

The output JSON structure MUST match this exact schema:
{
  "items": [
    {
      "description": "String - exact description from the list above",
      "label": "String - exact label name from the available labels list"
    }
  ]
}
PROMPT;
    }

    private static function plainTextHint(?string $description): string
    {
        if ($description === null || trim($description) === '') {
            return '';
        }

        $plain = html_entity_decode(strip_tags($description), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $plain) ?? '');
    }
}
