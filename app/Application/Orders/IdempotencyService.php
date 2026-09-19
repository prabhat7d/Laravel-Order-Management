<?php

namespace App\Application\Orders;

use App\Models\IdempotencyKey;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\UniqueConstraintViolationException;

class IdempotencyService
{
    public function getExisting(
        int $userId,
        string $key,
        string $requestHash,
    ): ?IdempotencyKey {
        $record = IdempotencyKey::query()
            ->where('user_id', $userId)
            ->where('key', $key)
            ->first();

        if (!$record) {
            return null;
        }

        if ($record->expires_at->isPast()) {
            $record->delete();

            return null;
        }

        if ($record->request_hash !== $requestHash) {
            throw ValidationException::withMessages([
                'Idempotency-Key' => [
                    'This idempotency key was already used with a different request.',
                ],
            ]);
        }

        return $record;
    }

    public function create(
        int $userId,
        string $key,
        string $requestHash,
    ): IdempotencyKey {
        try {
            return IdempotencyKey::create([
                'user_id' => $userId,
                'key' => $key,
                'request_hash' => $requestHash,
                'expires_at' => now()->addHours(24),
            ]);
        } catch (UniqueConstraintViolationException) {
            $existing = IdempotencyKey::query()
                ->where('user_id', $userId)
                ->where('key', $key)
                ->firstOrFail();

            if ($existing->request_hash !== $requestHash) {
                throw ValidationException::withMessages([
                    'Idempotency-Key' => [
                        'This idempotency key was already used with a different request.',
                    ],
                ]);
            }

            return $existing;
        }
    }

    public function storeResponse(
        IdempotencyKey $record,
        int $status,
        array $body,
    ): void {
        $record->update([
            'response_status' => $status,
            'response_body' => $body,
        ]);
    }
}
