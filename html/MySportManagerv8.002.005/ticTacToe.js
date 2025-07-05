let board = ['', '', '', '', '', '', '', '', ''];
let currentPlayer = 'X'; // 'X' starts by default
let gameOver = false;
let playerScore = 0;
let aiScore = 0;
let aiDifficulty = 1; // Default to Easy

const cells = document.querySelectorAll('.cell');
const resetButton = document.getElementById('reset');
const gameBoard = document.getElementById('game-board');
const aiStartCheckbox = document.getElementById('ai-start');
const playerScoreElement = document.getElementById('player-score');
const aiScoreElement = document.getElementById('ai-score');
const difficultySelect = document.getElementById('difficulty');

// Handle player move
function playerMove(cell, index) {
    if (!gameOver && board[index] === '') {
        board[index] = currentPlayer;
        cell.classList.add(currentPlayer.toLowerCase());
        cell.textContent = currentPlayer;
        checkWinner();
        if (!gameOver) {
            currentPlayer = currentPlayer === 'X' ? 'O' : 'X';
            if (currentPlayer === 'O') aiMove();
        }
    }
}

// Handle AI move
function aiMove() {
    let availableMoves = board
        .map((value, index) => value === '' ? index : null)
        .filter(index => index !== null);

    if (availableMoves.length === 0) return; // No moves available

    let bestMove;
    let minimaxResult;

    if (aiDifficulty === 1) {
        // Easy: Random move
        bestMove = availableMoves[Math.floor(Math.random() * availableMoves.length)];
    } else if (aiDifficulty === 2) {
        // Medium: Minimax with depth 3
        minimaxResult = minimax(board, 'O', 3);
        bestMove = minimaxResult.index;
    } else {
        // Hard: Full Minimax search
        minimaxResult = minimax(board, 'O', 6);
        bestMove = minimaxResult.index;
    }

    // Apply the AI's move
    board[bestMove] = 'O';
    cells[bestMove].classList.add('o');
    cells[bestMove].textContent = 'O';

    // Check for a winner or switch players
    checkWinner();
    if (!gameOver) {
        currentPlayer = 'X';
    }
}

// Check for a winner
function checkWinner() {
    const winningCombinations = [
        [0, 1, 2],
        [3, 4, 5],
        [6, 7, 8],
        [0, 3, 6],
        [1, 4, 7],
        [2, 5, 8],
        [0, 4, 8],
        [2, 4, 6],
    ];

    for (let combo of winningCombinations) {
        const [a, b, c] = combo;
        if (board[a] && board[a] === board[b] && board[a] === board[c]) {
            gameOver = true;
            if (board[a] === 'X') {
                playerScore += 2;  // Player wins
            } else {
                aiScore += 2;  // AI wins
            }
            updateScores();
            const message = document.createElement('div');
            message.textContent = `${currentPlayer} wins!`;
            message.style.position = 'absolute';
            message.style.top = '50%';
            message.style.left = '50%';
            message.style.whiteSpace = 'nowrap';
            message.style.transform = 'translate(-50%, -50%)';
            message.style.fontSize = '15em';
            message.style.color = currentPlayer === 'X' ? 'red' : 'blue';
            message.style.transition = 'opacity 6s';
            document.body.appendChild(message);
            setTimeout(() => message.style.opacity = '0', 100);
            message.addEventListener('click', () => {
                message.remove();
                resetGame()
            });
            setTimeout(() => message.remove(), 6100);
            return;
        }
    }

    if (!board.includes('')) {
        gameOver = true;
        playerScore += 1;
        aiScore += 1;
        updateScores();
        const message = document.createElement('div');
        message.textContent = "It's a draw!";
        message.style.position = 'absolute';
        message.style.top = '50%';
        message.style.left = '50%';
        message.style.whiteSpace = 'nowrap';
        message.style.transform = 'translate(-50%, -50%)';
        message.style.fontSize = '15em';
        message.style.color = 'black';
        message.style.transition = 'opacity 6s';
        document.body.appendChild(message);
        setTimeout(() => message.style.opacity = '0', 100);
        message.addEventListener('click', () => {
            message.remove();
            resetGame()
        });
        setTimeout(() => message.remove(), 6100);
    }
}

// Minimax AI algorithm for best move
function minimax(board, player, depth) {
    const availableMoves = board
        .map((value, index) => (value === '' ? index : null))
        .filter(index => index !== null);

    // Base cases: Check for win, loss, or draw
    if (checkBoard(board, 'X')) return { score: -1 };
    if (checkBoard(board, 'O')) return { score: 1 };
    if (availableMoves.length === 0 || depth === 0) return { score: 0 };

    // Store all possible moves
    const moves = [];

    // Iterate through available moves
    for (const move of availableMoves) {
        board[move] = player; // Make the move
        const score = minimax(board, player === 'O' ? 'X' : 'O', depth - 1).score;
        moves.push({ index: move, score }); // Store move and its score
        board[move] = ''; // Undo the move
    }

    // Choose the best move
    if (player === 'O') {
        // Maximizing player
        return moves.reduce((best, move) => (move.score > best.score ? move : best), { score: -Infinity });
    } else {
        // Minimizing player
        return moves.reduce((best, move) => (move.score < best.score ? move : best), { score: Infinity });
    }
}

// Check if there's a winner
function checkBoard(board, player) {
    const winningCombinations = [
        [0, 1, 2],
        [3, 4, 5],
        [6, 7, 8],
        [0, 3, 6],
        [1, 4, 7],
        [2, 5, 8],
        [0, 4, 8],
        [2, 4, 6],
    ];

    for (let combo of winningCombinations) {
        const [a, b, c] = combo;
        if (board[a] === player && board[b] === player && board[c] === player) {
            return true;
        }
    }
    return false;
}

// Update scores in the UI
function updateScores() {
    playerScoreElement.textContent = playerScore;
    aiScoreElement.textContent = aiScore;
}

// Reset the game
function resetGame() {
    resetButton.textContent = 'Next Round';
    board = ['', '', '', '', '', '', '', '', ''];
    currentPlayer = 'X';
    gameOver = false;
    cells.forEach(cell => {
        cell.textContent = '';
        cell.classList.remove('x', 'o');
    });

    if (aiStartCheckbox.checked) {
        currentPlayer = 'O';
        aiMove();
    }
}

cells.forEach(cell => {
    cell.addEventListener('click', () => {
        const index = cell.getAttribute('data-index');
        playerMove(cell, index);
    });
});

resetButton.addEventListener('click', resetGame);

// Listen to difficulty change
difficultySelect.addEventListener('change', (e) => {
    aiDifficulty = parseInt(e.target.value);  // Update difficulty based on selection
});

// If the AI is set to start first, make its first move on page load
if (aiStartCheckbox.checked) {
    currentPlayer = 'O';
    aiMove();
}
