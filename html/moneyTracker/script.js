// Split Wallet Tracker - JavaScript Logic

class SplitWalletTracker {
    constructor() {
        this.data = {
            dailySpend: {
                date: this.getCurrentDate(),
                earnings: 0,
                expenses: 0,
                limit: 0
            },
            reserveWallet: 0,
            reserveHistory: [],
            accounts: {
                'cash-wallet': { name: 'Cash (Wallet)', balance: 0 },
                'cash-stored': { name: 'Cash (Stored)', balance: 0 },
                'revolut': { name: 'Revolut', balance: 0 },
                'boi-current': { name: 'BOI Current Account', balance: 0 }
            }
        };
        
        this.initializeData();
        this.bindEvents();
        this.updateUI();
        this.setupMidnightReset();
    }

    getCurrentDate() {
        return new Date().toISOString().split('T')[0];
    }

    initializeData() {
        const savedData = localStorage.getItem('splitWalletData');
        if (savedData) {
            const parsed = JSON.parse(savedData);
            this.data = { ...this.data, ...parsed };
            
            // Ensure default accounts exist
            if (!this.data.accounts) {
                this.data.accounts = {
                    'cash-wallet': { name: 'Cash (Wallet)', balance: 0 },
                    'cash-stored': { name: 'Cash (Stored)', balance: 0 },
                    'revolut': { name: 'Revolut', balance: 0 },
                    'boi-current': { name: 'BOI Current Account', balance: 0 }
                };
            }
        }
        
        // Check if it's a new day and reset daily spend
        if (this.data.dailySpend.date !== this.getCurrentDate()) {
            this.resetDailySpend();
        }
    }

    saveData() {
        localStorage.setItem('splitWalletData', JSON.stringify(this.data));
    }

    bindEvents() {
        // Daily earnings
        document.getElementById('add-earnings').addEventListener('click', () => {
            const amount = parseFloat(document.getElementById('daily-earnings').value);
            if (!isNaN(amount) && amount > 0) {
                this.addEarnings(amount);
                document.getElementById('daily-earnings').value = '';
            }
        });

        // Daily expenses
        document.getElementById('add-expense').addEventListener('click', () => {
            const amount = parseFloat(document.getElementById('daily-expense').value);
            if (!isNaN(amount) && amount > 0) {
                this.addExpense(amount);
                document.getElementById('daily-expense').value = '';
            }
        });

        // Transfer to reserve
        document.getElementById('transfer-to-reserve').addEventListener('click', () => {
            const amount = parseFloat(document.getElementById('transfer-amount').value);
            if (!isNaN(amount) && amount > 0) {
                this.transferToReserve(amount);
                document.getElementById('transfer-amount').value = '';
            }
        });

        // Withdraw from reserve
        document.getElementById('withdraw-from-reserve').addEventListener('click', () => {
            const amount = parseFloat(document.getElementById('withdraw-amount').value);
            if (!isNaN(amount) && amount > 0) {
                this.withdrawFromReserve(amount);
                document.getElementById('withdraw-amount').value = '';
            }
        });

        // Add account
        document.getElementById('add-account').addEventListener('click', () => {
            const name = document.getElementById('new-account-name').value.trim();
            if (name) {
                this.addAccount(name);
                document.getElementById('new-account-name').value = '';
            }
        });

        // Transfer between accounts
        document.getElementById('transfer-between-accounts').addEventListener('click', () => {
            const fromAccount = document.getElementById('from-account').value;
            const toAccount = document.getElementById('to-account').value;
            const amount = parseFloat(document.getElementById('transfer-between-amount').value);
            
            if (fromAccount && toAccount && amount > 0 && fromAccount !== toAccount) {
                this.transferBetweenAccounts(fromAccount, toAccount, amount);
                document.getElementById('transfer-between-amount').value = '';
                document.getElementById('from-account').value = '';
                document.getElementById('to-account').value = '';
            }
        });

        // Reset daily spend
        document.getElementById('reset-daily').addEventListener('click', () => {
            if (confirm('Are you sure you want to reset today\'s daily spend? This will clear earnings and expenses for today.')) {
                this.resetDailySpend();
            }
        });

        // Clear all data
        document.getElementById('clear-all-data').addEventListener('click', () => {
            if (confirm('Are you sure you want to clear ALL data? This action cannot be undone.')) {
                this.clearAllData();
            }
        });

        // Enter key support for inputs
        ['daily-earnings', 'daily-expense', 'transfer-amount', 'withdraw-amount', 'new-account-name', 'transfer-between-amount'].forEach(id => {
            document.getElementById(id).addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    const buttonMap = {
                        'daily-earnings': 'add-earnings',
                        'daily-expense': 'add-expense',
                        'transfer-amount': 'transfer-to-reserve',
                        'withdraw-amount': 'withdraw-from-reserve',
                        'new-account-name': 'add-account',
                        'transfer-between-amount': 'transfer-between-accounts'
                    };
                    document.getElementById(buttonMap[id]).click();
                }
            });
        });
    }

    addEarnings(amount) {
        this.data.dailySpend.earnings += amount;
        this.data.dailySpend.limit = this.data.dailySpend.earnings;
        this.saveData();
        this.updateUI();
        this.showNotification(`Added €${amount.toFixed(2)} to earnings`, 'success');
    }

    addExpense(amount) {
        if (amount <= this.getRemainingDaily()) {
            this.data.dailySpend.expenses += amount;
            this.saveData();
            this.updateUI();
            this.showNotification(`Added €${amount.toFixed(2)} expense`, 'info');
        } else {
            this.showNotification('Expense exceeds remaining daily limit!', 'error');
        }
    }

    transferToReserve(amount) {
        const remaining = this.getRemainingDaily();
        if (amount <= remaining) {
            this.data.dailySpend.expenses += amount;
            this.data.reserveWallet += amount;
            this.updateReserveHistory();
            this.saveData();
            this.updateUI();
            this.showNotification(`Transferred €${amount.toFixed(2)} to reserve`, 'success');
        } else {
            this.showNotification('Transfer amount exceeds remaining daily limit!', 'error');
        }
    }

    withdrawFromReserve(amount) {
        if (amount <= this.data.reserveWallet) {
            if (confirm(`Are you sure you want to withdraw €${amount.toFixed(2)} from your reserve?`)) {
                this.data.reserveWallet -= amount;
                this.updateReserveHistory();
                this.saveData();
                this.updateUI();
                this.showNotification(`Withdrew €${amount.toFixed(2)} from reserve`, 'warning');
            }
        } else {
            this.showNotification('Withdrawal amount exceeds reserve balance!', 'error');
        }
    }

    addAccount(name) {
        const accountId = name.toLowerCase().replace(/[^a-z0-9]/g, '-');
        if (this.data.accounts[accountId]) {
            this.showNotification('Account with this name already exists!', 'error');
            return;
        }
        
        this.data.accounts[accountId] = {
            name: name,
            balance: 0
        };
        
        this.saveData();
        this.updateUI();
        this.showNotification(`Added account: ${name}`, 'success');
    }

    deleteAccount(accountId) {
        if (confirm(`Are you sure you want to delete the account "${this.data.accounts[accountId].name}"?`)) {
            delete this.data.accounts[accountId];
            this.saveData();
            this.updateUI();
            this.showNotification('Account deleted', 'info');
        }
    }

    updateAccountBalance(accountId, amount, operation) {
        if (operation === 'add') {
            this.data.accounts[accountId].balance += amount;
            this.showNotification(`Added €${amount.toFixed(2)} to ${this.data.accounts[accountId].name}`, 'success');
        } else if (operation === 'subtract') {
            if (amount <= this.data.accounts[accountId].balance) {
                this.data.accounts[accountId].balance -= amount;
                this.showNotification(`Subtracted €${amount.toFixed(2)} from ${this.data.accounts[accountId].name}`, 'info');
            } else {
                this.showNotification('Insufficient balance in account!', 'error');
                return false;
            }
        }
        
        this.saveData();
        this.updateUI();
        return true;
    }

    transferBetweenAccounts(fromAccountId, toAccountId, amount) {
        if (amount <= this.data.accounts[fromAccountId].balance) {
            this.data.accounts[fromAccountId].balance -= amount;
            this.data.accounts[toAccountId].balance += amount;
            
            this.saveData();
            this.updateUI();
            this.showNotification(`Transferred €${amount.toFixed(2)} from ${this.data.accounts[fromAccountId].name} to ${this.data.accounts[toAccountId].name}`, 'success');
        } else {
            this.showNotification('Insufficient balance in source account!', 'error');
        }
    }

    getTotalAccountBalance() {
        return Object.values(this.data.accounts).reduce((total, account) => total + account.balance, 0);
    }

    getRemainingDaily() {
        return Math.max(0, this.data.dailySpend.limit - this.data.dailySpend.expenses);
    }

    resetDailySpend() {
        // Save any remaining amount to reserve before reset
        const remaining = this.getRemainingDaily();
        if (remaining > 0) {
            this.data.reserveWallet += remaining;
            this.updateReserveHistory();
        }

        this.data.dailySpend = {
            date: this.getCurrentDate(),
            earnings: 0,
            expenses: 0,
            limit: 0
        };
        
        this.saveData();
        this.updateUI();
        this.showNotification('Daily spend reset. Remaining amount moved to reserve.', 'info');
    }

    setupMidnightReset() {
        const now = new Date();
        const tomorrow = new Date(now);
        tomorrow.setDate(tomorrow.getDate() + 1);
        tomorrow.setHours(0, 0, 0, 0);
        
        const msUntilMidnight = tomorrow.getTime() - now.getTime();
        
        // Set timeout for first midnight
        setTimeout(() => {
            this.resetDailySpend();
            
            // Set interval for subsequent midnights (every 24 hours)
            setInterval(() => {
                this.resetDailySpend();
            }, 24 * 60 * 60 * 1000);
        }, msUntilMidnight);
        
        // Also check every minute in case the page was left open across midnight
        setInterval(() => {
            if (this.data.dailySpend.date !== this.getCurrentDate()) {
                this.resetDailySpend();
            }
        }, 60000);
    }

    updateReserveHistory() {
        const today = this.getCurrentDate();
        const existingEntry = this.data.reserveHistory.find(entry => entry.date === today);
        
        if (existingEntry) {
            existingEntry.balance = this.data.reserveWallet;
        } else {
            this.data.reserveHistory.push({
                date: today,
                balance: this.data.reserveWallet
            });
        }

        // Keep only last 30 days
        this.data.reserveHistory = this.data.reserveHistory
            .sort((a, b) => new Date(b.date) - new Date(a.date))
            .slice(0, 30);
    }

    clearAllData() {
        this.data = {
            dailySpend: {
                date: this.getCurrentDate(),
                earnings: 0,
                expenses: 0,
                limit: 0
            },
            reserveWallet: 0,
            reserveHistory: [],
            accounts: {
                'cash-wallet': { name: 'Cash (Wallet)', balance: 0 },
                'cash-stored': { name: 'Cash (Stored)', balance: 0 },
                'revolut': { name: 'Revolut', balance: 0 },
                'boi-current': { name: 'BOI Current Account', balance: 0 }
            }
        };
        
        localStorage.removeItem('splitWalletData');
        this.updateUI();
        this.showNotification('All data cleared', 'info');
    }

    updateUI() {
        // Update daily spend displays
        document.getElementById('daily-limit').textContent = `€${this.data.dailySpend.limit.toFixed(2)}`;
        document.getElementById('total-spent').textContent = `€${this.data.dailySpend.expenses.toFixed(2)}`;
        document.getElementById('remaining-daily').textContent = `€${this.getRemainingDaily().toFixed(2)}`;

        // Update reserve display
        document.getElementById('reserve-balance').textContent = `€${this.data.reserveWallet.toFixed(2)}`;

        // Update progress bar
        this.updateProgressBar();

        // Update reserve chart
        this.updateReserveChart();

        // Update accounts
        this.updateAccountsDisplay();
        this.updateAccountSelects();
    }

    updateAccountsDisplay() {
        const accountsGrid = document.getElementById('accounts-grid');
        const totalBalance = this.getTotalAccountBalance();
        
        let html = `
            <div class="total-balance">
                <span class="label">Total Balance Across All Accounts</span>
                <span class="value">€${totalBalance.toFixed(2)}</span>
            </div>
        `;
        
        Object.entries(this.data.accounts).forEach(([accountId, account]) => {
            const isDefault = ['cash-wallet', 'cash-stored', 'revolut', 'boi-current'].includes(accountId);
            html += `
                <div class="account-card ${isDefault ? 'primary' : ''}">
                    ${!isDefault ? `<button class="delete-account" onclick="app.deleteAccount('${accountId}')">×</button>` : ''}
                    <div class="account-header">
                        <div class="account-name">${account.name}</div>
                        <div class="account-balance">€${account.balance.toFixed(2)}</div>
                    </div>
                    <div class="account-actions-inline">
                        <input type="number" placeholder="Amount" step="0.01" min="0" id="amount-${accountId}">
                        <button onclick="app.updateAccountBalance('${accountId}', parseFloat(document.getElementById('amount-${accountId}').value) || 0, 'add'); document.getElementById('amount-${accountId}').value = ''">Add</button>
                        <button onclick="app.updateAccountBalance('${accountId}', parseFloat(document.getElementById('amount-${accountId}').value) || 0, 'subtract'); document.getElementById('amount-${accountId}').value = ''" class="secondary">Subtract</button>
                    </div>
                </div>
            `;
        });
        
        accountsGrid.innerHTML = html;
    }

    updateAccountSelects() {
        const fromSelect = document.getElementById('from-account');
        const toSelect = document.getElementById('to-account');
        
        const options = Object.entries(this.data.accounts).map(([accountId, account]) => 
            `<option value="${accountId}">${account.name}</option>`
        ).join('');
        
        fromSelect.innerHTML = '<option value="">From Account</option>' + options;
        toSelect.innerHTML = '<option value="">To Account</option>' + options;
    }

    updateProgressBar() {
        const progressFill = document.getElementById('daily-progress');
        const progressText = document.getElementById('progress-percentage');
        
        let percentage = 0;
        if (this.data.dailySpend.limit > 0) {
            percentage = (this.data.dailySpend.expenses / this.data.dailySpend.limit) * 100;
        }
        
        percentage = Math.min(percentage, 100);
        
        progressFill.style.width = `${percentage}%`;
        progressText.textContent = `${percentage.toFixed(1)}%`;

        // Change color based on percentage
        if (percentage >= 90) {
            progressFill.style.background = 'linear-gradient(90deg, #e74c3c, #c0392b)';
        } else if (percentage >= 70) {
            progressFill.style.background = 'linear-gradient(90deg, #f39c12, #e67e22)';
        } else {
            progressFill.style.background = 'linear-gradient(90deg, #2ecc71, #27ae60)';
        }
    }

    updateReserveChart() {
        const chartContainer = document.getElementById('reserve-chart');
        
        if (this.data.reserveHistory.length === 0) {
            chartContainer.innerHTML = '<div class="chart-placeholder">Reserve history will appear here</div>';
            return;
        }

        // Create a simple text-based chart for now
        const sortedHistory = [...this.data.reserveHistory].sort((a, b) => new Date(a.date) - new Date(b.date));
        const maxBalance = Math.max(...sortedHistory.map(entry => entry.balance));
        
        let chartHTML = '<div class="reserve-chart-content">';
        chartHTML += '<h4>Recent Reserve History</h4>';
        chartHTML += '<div class="chart-bars">';
        
        sortedHistory.slice(-7).forEach(entry => {
            const height = maxBalance > 0 ? (entry.balance / maxBalance) * 100 : 0;
            const date = new Date(entry.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            
            chartHTML += `
                <div class="chart-bar-container">
                    <div class="chart-bar" style="height: ${height}%; background: linear-gradient(to top, #3498db, #2980b9);"></div>
                    <div class="chart-label">${date}</div>
                    <div class="chart-value">€${entry.balance.toFixed(0)}</div>
                </div>
            `;
        });
        
        chartHTML += '</div></div>';
        chartContainer.innerHTML = chartHTML;
    }

    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        
        // Add styles
        Object.assign(notification.style, {
            position: 'fixed',
            top: '20px',
            right: '20px',
            padding: '15px 20px',
            borderRadius: '8px',
            color: 'white',
            fontWeight: '600',
            zIndex: '1000',
            transform: 'translateX(100%)',
            transition: 'transform 0.3s ease',
            maxWidth: '300px',
            wordWrap: 'break-word'
        });

        // Set background color based on type
        const colors = {
            success: '#2ecc71',
            error: '#e74c3c',
            warning: '#f39c12',
            info: '#3498db'
        };
        notification.style.backgroundColor = colors[type] || colors.info;

        // Add to page
        document.body.appendChild(notification);

        // Animate in
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 100);

        // Remove after 3 seconds
        setTimeout(() => {
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 3000);
    }
}

// Add CSS for reserve chart
const chartStyles = `
    .reserve-chart-content {
        text-align: left;
    }
    
    .reserve-chart-content h4 {
        margin-bottom: 15px;
        color: #2c3e50;
        text-align: center;
    }
    
    .chart-bars {
        display: flex;
        justify-content: space-around;
        align-items: flex-end;
        height: 120px;
        padding: 10px 0;
        border-bottom: 2px solid #bdc3c7;
    }
    
    .chart-bar-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        flex: 1;
        margin: 0 2px;
    }
    
    .chart-bar {
        width: 100%;
        max-width: 30px;
        min-height: 5px;
        border-radius: 4px 4px 0 0;
        margin-bottom: 5px;
    }
    
    .chart-label {
        font-size: 0.8rem;
        color: #7f8c8d;
        margin-bottom: 2px;
    }
    
    .chart-value {
        font-size: 0.75rem;
        font-weight: 600;
        color: #2c3e50;
    }
`;

// Add chart styles to head
const styleSheet = document.createElement('style');
styleSheet.textContent = chartStyles;
document.head.appendChild(styleSheet);

// Initialize the app when DOM is loaded



// Add chart styles to head
const styleSheet = document.createElement("style");
styleSheet.textContent = chartStyles;
document.head.appendChild(styleSheet);

document.addEventListener("DOMContentLoaded", () => {
    window.app = new SplitWalletTracker(); // <-- expose globally for onclick
});

