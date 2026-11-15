<?php

use App\Support\Telephone;

it('normalises Malagasy phone numbers to the international format', function (?string $saisi, ?string $attendu): void {
    expect(Telephone::normaliser($saisi))->toBe($attendu);
})->with([
    'local avec espaces' => ['034 12 345 67', '+261341234567'],
    'local collé' => ['0321234567', '+261321234567'],
    'international' => ['+261 33 12 345 67', '+261331234567'],
    'sans plus' => ['261341234567', '+261341234567'],
    'double zéro' => ['0033612345678', '+33612345678'],
    'vide' => ['', null],
    'null' => [null, null],
]);

it('rejects what does not look like an international number', function (): void {
    expect(preg_match(Telephone::FORMAT, '+261341234567'))->toBe(1)
        ->and(preg_match(Telephone::FORMAT, (string) Telephone::normaliser('12')))->toBe(0);
});
