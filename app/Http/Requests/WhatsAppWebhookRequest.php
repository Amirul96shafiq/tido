<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\EvolutionCredential;
use App\Support\WhatsAppJid;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class WhatsAppWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        $authorization = $this->header('Authorization');
        $apiKey = trim((string) config('services.evolution.api_key'));
        $webhookSecret = trim((string) config('services.evolution.webhook_secret'));

        if (! EvolutionCredential::areDistinct($apiKey, $webhookSecret)
            || ! hash_equals('Bearer '.$webhookSecret, (string) $authorization)) {
            throw new HttpResponseException(response()->json(['error' => 'Unauthorized'], 401));
        }

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $event = (string) $this->input('event', '');
        $messageIdMax = max(1, (int) config('services.evolution.webhook_message_id_max', 128));
        $maxText = max(1, (int) config('services.evolution.webhook_max_text_chars', 8192));

        $rules = [
            'event' => ['required', 'string', 'max:64'],
            'instance' => [
                'sometimes',
                'string',
                'max:64',
                Rule::in([(string) config('services.evolution.instance_name', 'tido')]),
            ],
        ];

        if ($event !== 'messages.upsert') {
            return $rules;
        }

        $rules = array_merge($rules, [
            'data' => ['required', 'array'],
            'data.key' => ['required', 'array'],
            'data.key.remoteJid' => ['required', 'string', 'max:128'],
            'data.key.id' => [
                'required',
                'string',
                'max:'.$messageIdMax,
                'regex:/^[A-Za-z0-9._-]{1,'.$messageIdMax.'}$/',
            ],
            'data.key.fromMe' => ['required', 'boolean'],
            'data.messageType' => ['required', 'string', 'max:64'],
            'data.message' => ['sometimes', 'array'],
            'data.messageTimestamp' => ['sometimes', 'integer'],
            'data.pushName' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        $messageType = (string) $this->input('data.messageType', '');

        if ($messageType === 'conversation') {
            $rules['data.message.conversation'] = ['required', 'string', 'max:'.$maxText];
        }

        if ($messageType === 'extendedTextMessage') {
            $rules['data.message.extendedTextMessage'] = ['required', 'array'];
            $rules['data.message.extendedTextMessage.text'] = ['required', 'string', 'max:'.$maxText];
        }

        if ($messageType === 'imageMessage') {
            $rules['data.message'] = ['required', 'array'];
            $rules['data.message.imageMessage'] = ['present', 'array'];
        }

        if ($messageType === 'documentMessage') {
            $rules['data.message'] = ['required', 'array'];
            $rules['data.message.documentMessage'] = ['present', 'array'];
            $rules['data.message.documentMessage.mimetype'] = ['sometimes', 'nullable', 'string', 'max:128'];
            $rules['data.message.documentMessage.fileName'] = ['sometimes', 'nullable', 'string', 'max:255'];
            $rules['data.message.documentMessage.title'] = ['sometimes', 'nullable', 'string', 'max:255'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ((string) $this->input('event', '') !== 'messages.upsert') {
                return;
            }

            $data = $this->input('data');

            if (! is_array($data) || array_is_list($data)) {
                $validator->errors()->add('data', 'Invalid payload.');

                return;
            }

            $remoteJid = (string) data_get($data, 'key.remoteJid', '');

            if ($remoteJid !== '' && ! WhatsAppJid::isValidInbound($remoteJid)) {
                $validator->errors()->add('data.key.remoteJid', 'Invalid payload.');
            }

            $timestamp = data_get($data, 'messageTimestamp');

            if ($timestamp !== null && is_numeric($timestamp)) {
                $ts = (int) $timestamp;
                $maxFuture = now()->addMinutes(5)->getTimestamp();

                if ($ts > $maxFuture) {
                    $validator->errors()->add('data.messageTimestamp', 'Invalid payload.');
                }
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'error' => 'Invalid payload',
        ], 422));
    }
}
