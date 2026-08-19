<?php

namespace App\Livewire\Reports;

use App\Models\Account;
use App\Models\ExpenseTransaction;
use App\Models\IncomeTransaction;
use App\Services\FinancialReportService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ManageReports extends Component
{
    use WithPagination;

    #[Url(as: 'tab')]
    public string $activeTab = 'summary'; // summary | income | expense | transfers

    #[Url(as: 'period')]
    public string $period = 'this_month';

    #[Url(as: 'from')]
    public ?string $from = null;

    #[Url(as: 'to')]
    public ?string $to = null;

    #[Url(as: 'search')]
    public string $search = '';

    #[Url(as: 'account_id')]
    public string $account_id = '';

    #[Url(as: 'category')]
    public string $category = '';

    #[Url(as: 'payment_status')]
    public string $payment_status = 'all';

    #[Url(as: 'record_status')]
    public string $record_status = 'all';

    #[Url(as: 'from_account_id')]
    public string $from_account_id = '';

    #[Url(as: 'to_account_id')]
    public string $to_account_id = '';

    // Custom date inputs bound to form
    public ?string $customFrom = null;
    public ?string $customTo = null;

    public function mount(): void
    {
        $this->customFrom = $this->from;
        $this->customTo   = $this->to;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingAccountId(): void
    {
        $this->resetPage();
    }

    public function updatingCategory(): void
    {
        $this->resetPage();
    }

    public function updatingPaymentStatus(): void
    {
        $this->resetPage();
    }

    public function updatingRecordStatus(): void
    {
        $this->resetPage();
    }

    public function setTab(string $tab): void
    {
        $allowed = ['summary', 'income', 'expense', 'transfers'];
        if (in_array($tab, $allowed, true)) {
            $this->activeTab = $tab;
            $this->resetPage();
        }
    }

    public function setPresetPeriod(string $preset): void
    {
        $this->period = $preset;
        $this->from = null;
        $this->to = null;
        $this->customFrom = null;
        $this->customTo = null;
        $this->resetPage();
    }

    public function applyCustomDate(): void
    {
        $this->period = 'custom';
        $this->from   = $this->customFrom;
        $this->to     = $this->customTo;
        $this->resetPage();
    }

    public function getExportUrlProperty(): string
    {
        $params = [
            'period'         => $this->period,
            'from'           => $this->from,
            'to'             => $this->to,
            'search'         => $this->search,
            'account_id'     => $this->account_id,
            'category'       => $this->category,
            'payment_status' => $this->payment_status,
            'record_status'  => $this->record_status,
            'from_account_id'=> $this->from_account_id,
            'to_account_id'  => $this->to_account_id,
        ];

        return route('reports.export', array_merge(['type' => $this->activeTab], array_filter($params, fn($v) => $v !== null && $v !== '')));
    }

    public function render(FinancialReportService $reportService)
    {
        $dateRange = $reportService->resolveDateRange($this->period, $this->from, $this->to);
        $fromDate  = $dateRange['fromDate'];
        $toDate    = $dateRange['toDate'];
        $dateValidationError = $dateRange['dateValidationError'];
        $activePeriod = $dateRange['period'];

        $accounts = Account::where('is_active', true)->get();

        $incomeCategories = IncomeTransaction::select('category')->distinct()->pluck('category')->filter()->toArray();
        $expenseCategories = ExpenseTransaction::CATEGORIES;

        $summaryData = null;
        $incomeData  = null;
        $expenseData = null;
        $transferData = null;

        $filters = [
            'from'            => $fromDate,
            'to'              => $toDate,
            'account_id'      => $this->account_id,
            'category'        => $this->category,
            'payment_status'  => $this->payment_status,
            'record_status'   => $this->record_status,
            'from_account_id' => $this->from_account_id,
            'to_account_id'   => $this->to_account_id,
            'search'          => $this->search,
        ];

        if ($this->activeTab === 'summary') {
            $summaryData = $reportService->getFinancialSummary($fromDate, $toDate);
        } elseif ($this->activeTab === 'income') {
            $incomeData = $reportService->getIncomeQuery($filters)->paginate(8);
        } elseif ($this->activeTab === 'expense') {
            $expenseData = $reportService->getExpenseQuery($filters)->paginate(8);
        } elseif ($this->activeTab === 'transfers') {
            $transferData = $reportService->getTransferQuery($filters)->paginate(8);
        }

        return view('livewire.reports.manage-reports', [
            'fromDate'            => $fromDate,
            'toDate'              => $toDate,
            'dateValidationError' => $dateValidationError,
            'activePeriod'        => $activePeriod,
            'accounts'            => $accounts,
            'incomeCategories'    => $incomeCategories,
            'expenseCategories'   => $expenseCategories,
            'summaryData'         => $summaryData,
            'incomeData'          => $incomeData,
            'expenseData'         => $expenseData,
            'transferData'        => $transferData,
            'exportUrl'           => $this->exportUrl,
        ]);
    }
}
