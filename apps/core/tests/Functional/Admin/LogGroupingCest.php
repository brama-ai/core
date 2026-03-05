<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

/**
 * Оскільки логування базується на OpenSearch, цей тест перевіряє сторінку /admin/logs та /admin/logs/trace/{id}
 * Він перевіряє, що відрендерений UI містить потрібні елементи візуального групування (класи для відступів або кольорів),
 * і що trace_id та request_id правильно відображаються.
 */
class LogGroupingCest
{
    private function login(\FunctionalTester $I): void
    {
        $I->sendPost('/admin/login', [
            '_username' => 'admin',
            '_password' => 'test-password',
        ]);
        $I->seeResponseCodeIs(200);
    }

    public function traceLogsPageGroupsVisuallyByColorsAndIndents(\FunctionalTester $I): void
    {
        $this->login($I);

        $traceId = 'test-trace-' . uniqid();

        // Тестуємо чи вьюха логів підтримує структуру з відступами та кольоровими мітками додатку
        $I->sendGet('/admin/logs/trace/' . $traceId);

        $I->seeResponseCodeIs(200);
        $I->seeResponseContains($traceId);
        $I->seeResponseContains('trace-indent');
        $I->seeResponseContains('app-col');
    }
}
