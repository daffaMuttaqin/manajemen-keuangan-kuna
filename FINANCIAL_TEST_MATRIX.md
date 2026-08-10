# FINANCIAL TEST MATRIX

Financial correctness is the highest testing priority.

## Mandatory tests

### FT-001 Paid income once
Given an active account and paid income, when created, the account increases exactly once and Revenue includes the income once.

### FT-002 Unpaid income
Given unpaid income, when created, cash, Revenue, Gross Profit, and Net Profit remain unchanged; the outstanding receivable increases.

### FT-003 Paid expense once
Given an active account with sufficient balance, when a paid expense is created, the account decreases exactly once and the expense enters reporting.

### FT-004 Unpaid expense
Given unpaid expense, when created, cash and profit metrics remain unchanged; outstanding payable increases.

### FT-005 Duplicate payment
Given a transaction already Paid, when payment confirmation is submitted again, no financial amount changes.

### FT-006 Income payment confirmation
Given unpaid income Rp500.000, when payment is confirmed to an active account, account increases Rp500.000 exactly once, Revenue becomes active, and the receivable settles.

### FT-007 Expense payment confirmation
Given unpaid expense Rp500.000, when payment is confirmed, account decreases Rp500.000 exactly once, Expense becomes active, and payable settles.

### FT-008 Transfer conservation
Given BCA Rp1.000.000 and Cash Rp500.000, transfer Rp300.000 BCA -> Cash. Expected BCA Rp700.000, Cash Rp800.000, total Rp1.500.000.

### FT-009 Same-account transfer
Source and destination equal. Operation rejected and no balances change.

### FT-010 Insufficient transfer
Given source Rp500.000, transfer Rp1.000.000. Operation rejected and no balances change.

### FT-011 Loan receipt
Loan receipt increases account and outstanding loan principal but does not affect Revenue, COGS, OpEx, Gross Profit, or Net Profit.

### FT-012 Loan principal repayment
Repayment decreases account and outstanding principal but does not become ordinary Expense and does not affect Net Profit.

### FT-013 Opening balance
Opening balance changes Current Balance but does not change Revenue or Profit.

### FT-014 Asset purchase
Asset purchase decreases cash and creates asset record but does not reduce Net Profit in V1.

### FT-015 Cancellation paid income
Cancelling paid income reverses its active cash/reporting effect, preserves history, and creates an audit record.

### FT-016 Cancellation paid expense
Cancelling paid expense reverses its active cash/reporting effect, preserves history, and creates an audit record.

### FT-017 Cancellation unpaid
Cancelling unpaid income/expense removes the outstanding obligation effect while preserving history.

### FT-018 Historical price
Given historical unit price Rp100.000, changing current Menu price to Rp120.000 leaves historical transaction at Rp100.000.

### FT-019 Server recalculation
Manipulate client-submitted subtotal/discount/total. Server ignores authoritative client totals and recalculates from trusted inputs.

### FT-020 Inactive account
An inactive account cannot be selected for new paid financial activity.

### FT-021 Atomic rollback
Force a failure halfway through a multi-record financial operation. All financial changes roll back.

### FT-022 Duplicate request
Submit the same financial action twice. Financial effect occurs at most once.

### FT-023 Paid transaction edit amount
Old paid amount Rp300.000 -> new Rp350.000. Only the Rp50.000 difference is applied to the relevant account/reporting state.

### FT-024 Paid transaction edit account
Move a paid transaction from Account A to Account B. Reverse the old effect and apply the new effect atomically.

### FT-025 Date-range reporting
All period metrics respect the selected reporting range.

### FT-026 CSV filters
CSV export contains rows matching the active report filters and does not modify financial data.

### FT-027 Empty period
Dashboard/report displays zero metrics and correct empty states.

### FT-028 Receivable no double count
Unpaid income creates an outstanding receivable but does not enter Revenue until paid.

### FT-029 Payable no double count
Unpaid expense creates an outstanding payable but does not enter Expense until paid.

### FT-030 Auditability
Creation, payment, edit, cancellation, account changes, menu changes, loan movements, and other required financial actions create appropriate audit records without secrets/passwords.

## Regression rule

Any feature touching financial calculations must run:
1. the tests directly related to the feature;
2. all affected invariant tests;
3. the broader test suite before declaring the phase complete.
