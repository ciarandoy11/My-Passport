// connect4_with_ai.js

// Connect 4 with AI (Minimax Algorithm)

// Game setup
const ROWS = 6;
const COLUMNS = 7;
const board = Array.from({ length: ROWS }, () => Array(COLUMNS).fill(''));
let currentPlayer = 'X';
let gameOver = false;
let playerScore = 0;
let aiScore = 0;
const aiPlayer = 'O';
const humanPlayer = 'X';
const difficultySelect = document.getElementById('difficulty');
let aiDifficulty = 1; // Default to Easy

// Create the game board in HTML
const gameContainer = document.getElementById('game');
const aiP = document.getElementById('ai');
const playerP = document.getElementById('player');
function renderBoard() {
    gameContainer.innerHTML = '';
    for (let row = 0; row < ROWS; row++) {
        for (let col = 0; col < COLUMNS; col++) {
            const cell = document.createElement('div');
            cell.classList.add('cell');
            cell.dataset.row = row;
            cell.dataset.col = col;
            if (board[row][col] === currentPlayer) {
                cell.dataset.player = currentPlayer; // Added line to tell the css what to color
            }
            if (board[row][col] === (currentPlayer === 'X' ? 'O' : 'X')) {
                cell.dataset.player = currentPlayer === 'X' ? 'O' : 'X'; //the opposite of current player
                gameContainer.dataset.player = currentPlayer === 'X' ? 'O' : 'X'; //the opposite of current player
                aiP.style.color = currentPlayer === 'X' ? 'white' : 'blue';
                aiP.style.backgroundColor = currentPlayer === 'X' ? 'blue' : '';
                playerP.style.color = currentPlayer === 'O' ? 'white' : 'red';
                playerP.style.backgroundColor = currentPlayer === 'O' ? 'red' : '';
            }
            cell.textContent = board[row][col];
            cell.addEventListener('click', () => handleMove(col));
            gameContainer.appendChild(cell);
        }
    }
}

// Check if a move is valid
function isValidMove(col) {
    return board[0][col] === '';
}

// Make a move
function makeMove(col, player) {
    for (let row = ROWS - 1; row >= 0; row--) {
        if (board[row][col] === '') {
            board[row][col] = player;
            return;
        }
    }
}

// Undo a move
function undoMove(col) {
    for (let row = 0; row < ROWS; row++) {
        if (board[row][col] !== '') {
            board[row][col] = '';
            return;
        }
    }
}

// Check for a winner
function checkWinner(player) {
    // Check horizontal, vertical, and diagonal lines
    for (let row = 0; row < ROWS; row++) {
        for (let col = 0; col < COLUMNS; col++) {
            if (
                checkDirection(row, col, 0, 1, player) || // Horizontal
                checkDirection(row, col, 1, 0, player) || // Vertical
                checkDirection(row, col, 1, 1, player) || // Diagonal (\)
                checkDirection(row, col, 1, -1, player)   // Diagonal (/)
            ) {
                return true;
            }
        }
    }
    return false;
}

function checkDirection(row, col, rowDir, colDir, player) {
    let count = 0;
    for (let i = 0; i < 4; i++) {
        const r = row + i * rowDir;
        const c = col + i * colDir;
        if (r >= 0 && r < ROWS && c >= 0 && c < COLUMNS && board[r][c] === player) {
            count++;
        } else {
            break;
        }
    }
    return count === 4;
}

// Minimax algorithm
function minimax(board, depth, isMaximizing) {
    if (checkWinner(aiPlayer)) return { score: 2 };
    if (checkWinner(humanPlayer)) return { score: -2 };
    if (board[0].every(cell => cell !== '')) return { score: 0 }; // Draw

    if (depth === 0) return { score: 0 };

    const moves = [];
    for (let col = 0; col < COLUMNS; col++) {
        if (isValidMove(col)) {
            makeMove(col, isMaximizing ? aiPlayer : humanPlayer);
            const score = minimax(board, depth - 1, !isMaximizing).score;
            moves.push({ col, score });
            undoMove(col);
        }
    }

    const bestMove = moves.reduce((best, move) => (isMaximizing ? move.score > best.score : move.score < best.score) ? move : best, { score: isMaximizing ? -Infinity : Infinity });
    return bestMove;
}

// Handle a player's move
function handleMove(col) {
    if (gameOver || !isValidMove(col)) return;

    makeMove(col, currentPlayer);
    renderBoard();

    if (checkWinner(currentPlayer)) {
        if (currentPlayer === 'X') {
            playerScore++;
            playerScore++;
            document.getElementById('player-score').textContent = playerScore;
        }

        const message = document.createElement('div');
        message.textContent = `You win!`;
        message.style.position = 'absolute';
        message.style.top = '50%';
        message.style.left = '50%';
        message.style.whiteSpace = 'nowrap';
        message.style.transform = 'translate(-50%, -50%)';
        message.style.fontSize = '10em';
        message.style.color = 'black';
        message.style.transition = 'opacity 6s';
        document.body.appendChild(message);
        setTimeout(() => message.style.opacity = '0', 100);
        message.addEventListener('click', () => {
            message.remove();
            resetGame()
        });
        setTimeout(() => message.remove(), 6100);

        gameOver = true;
        return;
    }

    currentPlayer = currentPlayer === 'X' ? 'O' : 'X';

    if (currentPlayer === aiPlayer) {
        setTimeout(aiMove, 500); // Give a slight delay for AI
    }
}

// AI move
function aiMove() {
    let availableMoves = [];
    for (let col = 0; col < COLUMNS; col++) {
        if (isValidMove(col)) {
            availableMoves.push(col);
        }
    }

    if (availableMoves.length === 0) return; // No moves available

    let bestMove;
    let minimaxResult;

    if (aiDifficulty === 1) {
        // Easy: Random move
        bestMove = availableMoves[Math.floor(Math.random() * availableMoves.length)];
    } else if (aiDifficulty === 2) {
        // Medium: Minimax with depth 5
        minimaxResult = minimax(board, 5, true);
        bestMove = minimaxResult.col;
    } else {
        // Hard: Full Minimax search
        minimaxResult = minimax(board, 7, true);
        bestMove = minimaxResult.col;
    }

    // Apply the AI's move
    makeMove(bestMove, aiPlayer);
    renderBoard();

    // Check for a winner or switch players
    if (checkWinner(aiPlayer)) {
        aiScore += 2;  // AI wins
        document.getElementById('ai-score').textContent = aiScore;

        const message = document.createElement('div');
        message.textContent = "Ai Wins!";
        message.style.position = 'absolute';
        message.style.top = '50%';
        message.style.left = '50%';
        message.style.whiteSpace = 'nowrap';
        message.style.transform = 'translate(-50%, -50%)';
        message.style.fontSize = '10em';
        message.style.color = 'black';
        message.style.transition = 'opacity 6s';
        document.body.appendChild(message);
        setTimeout(() => message.style.opacity = '0', 100);
        message.addEventListener('click', () => {
            message.remove();
            resetGame()
        });
        setTimeout(() => message.remove(), 6100);

        gameOver = true;
        return;
    }

    if (board[0].every(cell => cell !== '')) {
        aiScore++;
        playerScore++;
        document.getElementById('ai-score').textContent = aiScore;
        document.getElementById('player-score').textContent = playerScore;

        const message = document.createElement('div');
        message.textContent = "It's a draw!";
        message.style.position = 'absolute';
        message.style.top = '50%';
        message.style.left = '50%';
        message.style.whiteSpace = 'nowrap';
        message.style.transform = 'translate(-50%, -50%)';
        message.style.fontSize = '10em';
        message.style.color = 'black';
        message.style.transition = 'opacity 6s';
        document.body.appendChild(message);
        setTimeout(() => message.style.opacity = '0', 100);
        message.addEventListener('click', () => {
            message.remove();
            resetGame()
        });
        setTimeout(() => message.remove(), 6100);

        gameOver = true;
        return;
    }

    currentPlayer = humanPlayer;
}

// Reset the game
function resetGame() {
    board.forEach(row => row.fill(''));
    currentPlayer = 'X';
    gameOver = false;
    renderBoard();
}

// Event listener for reset button
document.getElementById('reset').addEventListener('click', resetGame);

// Listen to difficulty change
difficultySelect.addEventListener('change', (e) => {
    aiDifficulty = parseInt(e.target.value);  // Update difficulty based on selection
});

// Start the game
renderBoard();
