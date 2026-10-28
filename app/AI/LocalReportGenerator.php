<?php

declare(strict_types=1);

namespace SanadAi\AI;

/**
 * Safe V1 AI/report layer: deterministic summaries only.
 * A real LLM adapter can be added later without changing the ERP API.
 */
final class LocalReportGenerator
{
    public function generate(array $stats): string
    {
        $lines = [
            'ملخص تشغيلي آلي لمكتب سند:',
            'إجمالي المعاملات: ' . (int)($stats['transactions'] ?? 0),
            'المعاملات المكتملة: ' . (int)($stats['completed'] ?? 0),
            'العملاء الجدد: ' . (int)($stats['new_clients'] ?? 0),
            'عمليات التخليص المفتوحة: ' . (int)($stats['open_clearance'] ?? 0),
            'الإيرادات المسجلة: ' . number_format(((int)($stats['revenue_baisa'] ?? 0)) / 1000, 3) . ' ر.ع.',
            '',
            'ملاحظة: هذا التقرير مساعد تحليلي ولا يتخذ قرارات حكومية أو قانونية ولا يخترع رسومًا أو إجراءات.',
        ];

        return implode("\n", $lines);
    }
}
