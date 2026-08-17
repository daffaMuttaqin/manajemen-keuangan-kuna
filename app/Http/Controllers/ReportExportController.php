<?php

namespace App\Http\Controllers;

use App\Models\ExpenseTransaction;
use App\Services\FinancialReportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    protected FinancialReportService $reportService;

    public function __construct(FinancialReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Download CSV report stream based on export type and filters.
     */
    public function export(Request $request, string $type): StreamedResponse
    {
        $allowedTypes = ['summary', 'income', 'expense', 'transfers'];

        if (!in_array($type, $allowedTypes, true)) {
            abort(404, 'Unsupported export report type.');
        }

        $period = $request->query('period', 'this_month');
        $from   = $request->query('from');
        $to     = $request->query('to');

        $dateRange = $this->reportService->resolveDateRange($period, $from, $to);
        $fromDate  = $dateRange['fromDate'];
        $toDate    = $dateRange['toDate'];

        $dateSuffix = $fromDate && $toDate ? "{$fromDate}_to_{$toDate}" : 'all_time';
        $filename   = "{$type}_report_{$dateSuffix}.csv";

        return response()->streamDownload(function () use ($type, $request, $fromDate, $toDate) {
            $handle = fopen('php://output', 'w');

            // Write UTF-8 BOM for Excel / spreadsheet encoding compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            if ($type === 'summary') {
                $this->exportSummaryCsv($handle, $fromDate, $toDate);
            } elseif ($type === 'income') {
                $this->exportIncomeCsv($handle, $request, $fromDate, $toDate);
            } elseif ($type === 'expense') {
                $this->exportExpenseCsv($handle, $request, $fromDate, $toDate);
            } elseif ($type === 'transfers') {
                $this->exportTransferCsv($handle, $request, $fromDate, $toDate);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Stream Financial Summary (P&L) CSV.
     */
    protected function exportSummaryCsv($handle, ?string $from, ?string $to): void
    {
        $summary = $this->reportService->getFinancialSummary($from, $to);

        // Header / Metadata
        fputcsv($handle, ['Kuna Patisserie - Financial Summary Report']);
        fputcsv($handle, ['Reporting Period', $from && $to ? "{$from} to {$to}" : 'All-Time']);
        fputcsv($handle, ['Generated At', now()->format('Y-m-d H:i:s')]);
        fputcsv($handle, []);

        // Financial Summary Metrics
        fputcsv($handle, ['Metric', 'Amount (IDR)']);
        fputcsv($handle, ['Total Revenue', number_format($summary['total_revenue'], 2, '.', '')]);
        fputcsv($handle, ['COGS (Cost of Goods Sold)', number_format($summary['cogs'], 2, '.', '')]);
        fputcsv($handle, ['Gross Profit', number_format($summary['gross_profit'], 2, '.', '')]);
        fputcsv($handle, ['Profit-Eligible OpEx', number_format($summary['profit_eligible_opex'], 2, '.', '')]);
        fputcsv($handle, ['Total Expenses (includes Asset)', number_format($summary['total_expenses'], 2, '.', '')]);
        fputcsv($handle, ['Asset Expenses', number_format($summary['asset_expenses'], 2, '.', '')]);
        fputcsv($handle, ['Net Profit', number_format($summary['net_profit'], 2, '.', '')]);
        fputcsv($handle, []);

        // Expense Category Breakdown
        fputcsv($handle, ['Expense Category Breakdown']);
        fputcsv($handle, ['Category Name', 'Paid Transaction Count', 'Total Amount (IDR)', 'Net Profit Impact']);
        foreach ($summary['category_breakdown'] as $row) {
            fputcsv($handle, [
                $row['category'],
                $row['count'],
                number_format($row['total_amount'], 2, '.', ''),
                $row['net_profit_impact'],
            ]);
        }
    }

    /**
     * Stream Income Detailed Report CSV in memory-safe chunks.
     */
    protected function exportIncomeCsv($handle, Request $request, ?string $from, ?string $to): void
    {
        $filters = [
            'from'           => $from,
            'to'             => $to,
            'account_id'     => $request->query('account_id'),
            'category'       => $request->query('category'),
            'payment_status' => $request->query('payment_status', 'all'),
            'record_status'  => $request->query('record_status', 'all'),
            'search'         => $request->query('search'),
        ];

        fputcsv($handle, [
            'Transaction ID',
            'Transaction Date',
            'Menu Item',
            'Category',
            'Quantity',
            'Unit Price',
            'Subtotal',
            'Discount %',
            'Discount Amount',
            'Total Amount',
            'Account Name',
            'Payment Status',
            'Record Status',
            'Paid At',
        ]);

        $query = $this->reportService->getIncomeQuery($filters);

        $query->chunk(500, function ($transactions) use ($handle) {
            foreach ($transactions as $tx) {
                fputcsv($handle, [
                    $tx->transaction_id,
                    $tx->transaction_date ? $tx->transaction_date->format('Y-m-d') : '',
                    $tx->menuItem->name ?? 'N/A',
                    $tx->category,
                    $tx->quantity,
                    number_format((float) $tx->unit_price, 2, '.', ''),
                    number_format((float) $tx->subtotal, 2, '.', ''),
                    number_format((float) $tx->discount_percentage, 2, '.', ''),
                    number_format((float) $tx->discount_amount, 2, '.', ''),
                    number_format((float) $tx->total_amount, 2, '.', ''),
                    $tx->account->name ?? 'N/A',
                    ucfirst($tx->payment_status),
                    ucfirst($tx->record_status),
                    $tx->paid_at ? $tx->paid_at->format('Y-m-d H:i:s') : '',
                ]);
            }
        });
    }

    /**
     * Stream Expense Detailed Report CSV in memory-safe chunks.
     */
    protected function exportExpenseCsv($handle, Request $request, ?string $from, ?string $to): void
    {
        $filters = [
            'from'           => $from,
            'to'             => $to,
            'account_id'     => $request->query('account_id'),
            'category'       => $request->query('category'),
            'payment_status' => $request->query('payment_status', 'all'),
            'record_status'  => $request->query('record_status', 'all'),
            'search'         => $request->query('search'),
        ];

        fputcsv($handle, [
            'Transaction ID',
            'Transaction Date',
            'Transaction Name',
            'Expense Category',
            'Description',
            'Amount',
            'Account Name',
            'Profit Eligible',
            'Payment Status',
            'Record Status',
            'Paid At',
        ]);

        $query = $this->reportService->getExpenseQuery($filters);

        $query->chunk(500, function ($transactions) use ($handle) {
            foreach ($transactions as $tx) {
                $profitEligible = 'Yes';
                if ($tx->expense_category === 'Asset') {
                    $profitEligible = 'No (Asset)';
                } elseif (!in_array($tx->expense_category, ExpenseTransaction::PROFIT_ELIGIBLE_CATEGORIES, true)) {
                    $profitEligible = 'No';
                }

                fputcsv($handle, [
                    $tx->transaction_id,
                    $tx->transaction_date ? $tx->transaction_date->format('Y-m-d') : '',
                    $tx->transaction_name,
                    $tx->expense_category,
                    $tx->description ?? '',
                    number_format((float) $tx->amount, 2, '.', ''),
                    $tx->account->name ?? 'N/A',
                    $profitEligible,
                    ucfirst($tx->payment_status),
                    ucfirst($tx->record_status),
                    $tx->paid_at ? $tx->paid_at->format('Y-m-d H:i:s') : '',
                ]);
            }
        });
    }

    /**
     * Stream Transfer Detailed Report CSV in memory-safe chunks.
     */
    protected function exportTransferCsv($handle, Request $request, ?string $from, ?string $to): void
    {
        $filters = [
            'from'            => $from,
            'to'              => $to,
            'from_account_id' => $request->query('from_account_id'),
            'to_account_id'   => $request->query('to_account_id'),
            'record_status'   => $request->query('record_status', 'all'),
            'search'          => $request->query('search'),
        ];

        fputcsv($handle, [
            'Transfer ID',
            'Transfer Date',
            'From Account',
            'To Account',
            'Amount',
            'Description',
            'Record Status',
        ]);

        $query = $this->reportService->getTransferQuery($filters);

        $query->chunk(500, function ($transfers) use ($handle) {
            foreach ($transfers as $tx) {
                fputcsv($handle, [
                    $tx->transfer_id,
                    $tx->transfer_date ? $tx->transfer_date->format('Y-m-d') : '',
                    $tx->fromAccount->name ?? 'N/A',
                    $tx->toAccount->name ?? 'N/A',
                    number_format((float) $tx->amount, 2, '.', ''),
                    $tx->description ?? '',
                    ucfirst($tx->record_status),
                ]);
            }
        });
    }
}
