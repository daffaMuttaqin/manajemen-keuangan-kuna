<?php

namespace App\Livewire\Transfer;

use App\Models\Account;
use App\Models\Transfer;
use App\Services\TransferService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ManageTransfers extends Component
{
    use WithPagination;

    // -------------------------------------------------------------------------
    // Form fields
    // -------------------------------------------------------------------------
    public string $transfer_date = '';
    public string $from_account_id = '';
    public string $to_account_id = '';
    public string $amount = '';
    public string $description = '';

    // -------------------------------------------------------------------------
    // UI state
    // -------------------------------------------------------------------------
    public bool $isModalOpen = false;
    public bool $isCancelModalOpen = false;
    public ?int $cancellingTransferId = null;

    // -------------------------------------------------------------------------
    // URL-bound search/filter state
    // -------------------------------------------------------------------------
    #[Url(as: 'search')]
    public string $search = '';

    #[Url(as: 'record')]
    public string $recordFilter = 'active';  // active | cancelled | all

    // -------------------------------------------------------------------------
    // Validation rules
    // -------------------------------------------------------------------------
    protected function rules(): array
    {
        return [
            'transfer_date'   => 'required|date',
            'from_account_id' => 'required|integer|exists:accounts,id',
            'to_account_id'   => 'required|integer|exists:accounts,id|different:from_account_id',
            'amount'          => 'required|numeric|gt:0',
            'description'     => 'nullable|string|max:1000',
        ];
    }

    protected array $messages = [
        'to_account_id.different' => 'Destination account must be different from source account. (INV-015)',
        'amount.gt'               => 'Transfer amount must be greater than zero.',
    ];

    public function mount(): void
    {
        $this->transfer_date = now()->format('Y-m-d');
    }

    public function updated($propertyName): void
    {
        $this->validateOnly($propertyName);
    }

    public function openModal(): void
    {
        $this->resetForm();
        $this->isModalOpen = true;
    }

    public function closeModal(): void
    {
        $this->isModalOpen = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->transfer_date   = now()->format('Y-m-d');
        $this->from_account_id = '';
        $this->to_account_id   = '';
        $this->amount          = '';
        $this->description     = '';
        $this->resetValidation();
    }

    public function saveTransfer(): void
    {
        $this->validate();

        $service = app(TransferService::class);

        try {
            $service->createTransfer([
                'transfer_date'   => $this->transfer_date,
                'from_account_id' => (int) $this->from_account_id,
                'to_account_id'   => (int) $this->to_account_id,
                'amount'          => $this->amount,
                'description'     => $this->description ?: null,
            ], auth()->user());

            session()->flash('success', 'Fund transfer completed successfully.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
            return;
        }

        $this->isModalOpen = false;
        $this->resetForm();
    }

    // -------------------------------------------------------------------------
    // Cancellation Workflow
    // -------------------------------------------------------------------------
    public function openCancelModal(int $id): void
    {
        $transfer = Transfer::findOrFail($id);

        if ($transfer->isCancelled()) {
            session()->flash('error', 'This transfer is already cancelled.');
            return;
        }

        $this->cancellingTransferId = $transfer->id;
        $this->isCancelModalOpen = true;
    }

    public function closeCancelModal(): void
    {
        $this->isCancelModalOpen = false;
        $this->cancellingTransferId = null;
    }

    public function cancelTransfer(): void
    {
        if (!$this->cancellingTransferId) {
            return;
        }

        $transfer = Transfer::findOrFail($this->cancellingTransferId);

        try {
            $service = app(TransferService::class);
            $service->cancelTransfer($transfer, auth()->user());

            session()->flash('success', 'Transfer cancelled successfully. Account balances updated.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->isCancelModalOpen = false;
        $this->cancellingTransferId = null;
    }

    public function render()
    {
        $query = Transfer::with(['fromAccount', 'toAccount', 'creator']);

        if ($this->recordFilter !== 'all') {
            $query->where('record_status', $this->recordFilter);
        }

        if (trim($this->search) !== '') {
            $term = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('description', 'like', $term)
                  ->orWhereHas('fromAccount', fn ($aq) => $aq->where('name', 'like', $term))
                  ->orWhereHas('toAccount', fn ($aq) => $aq->where('name', 'like', $term));
            });
        }

        $transfers = $query->orderBy('transfer_date', 'desc')
                           ->orderBy('id', 'desc')
                           ->paginate(10);

        $accounts = Account::where('is_active', true)->orderBy('name')->get();

        return view('livewire.transfer.manage-transfers', [
            'transfers' => $transfers,
            'accounts'  => $accounts,
        ]);
    }
}
