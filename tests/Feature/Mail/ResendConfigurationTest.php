<?php

use Illuminate\Mail\MailManager;

describe('Resend Service Mail Configuration', function () {

    it('uses the resend transport', function () {
        expect(config('mail.mailers.resend.transport'))
            ->toBe('resend');
    });

    it('can resolve the resend mailer', function () {
        config([
            'services.resend.key' => 're_test_key',
        ]);

        $mailer = app(MailManager::class)->mailer('resend');

        expect($mailer)->not->toBeNull();
    });

});
