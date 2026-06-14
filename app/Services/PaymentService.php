<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

class PaymentService
{
    public function charge(User $user, float $amount, array $context = []): array
    {
        if ($amount <= 0) {
            throw new RuntimeException('Payment amount must be greater than zero.');
        }

        if (! empty($context['simulate_failure'])) {
            throw new RuntimeException('Payment was intentionally declined for testing.');
        }

        return [
            'status' => 'paid',
            'reference' => 'PAY-' . strtoupper(Str::random(12)),
            'amount' => round($amount, 2),
            'currency' => 'USD',
            'user_id' => $user->id,
        ];
    }
}
