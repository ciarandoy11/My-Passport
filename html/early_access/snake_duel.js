const canvas = document.getElementById('gameCanvas');
const ctx = canvas.getContext('2d');

const CELL_SIZE = 20;
const ROWS = canvas.height / CELL_SIZE;
const COLS = canvas.width / CELL_SIZE;

let snake1 = [{ x: 5, y: 5 }];
let snake2 = [{ x: COLS - 6, y: ROWS - 6 }];

let playerScore = 0;
let aiScore = 0;

const playerScoreElement = document.getElementById('score1');
const aiScoreElement = document.getElementById('score2');

let direction1 = { x: 1, y: 0 }; // Snake 1 starts moving right
let direction2 = { x: -1, y: 0 }; // Snake 2 starts moving left (AI)

let food = spawnFood();

let gameOver = false;

// Main game loop
function gameLoop() {
    if (gameOver) return;

    update();
    draw();

    setTimeout(gameLoop, 100);
}

// Update game state
function update() {
    moveSnake(snake1, direction1);
    aiMove();
    
    if (checkCollision(snake1) || checkSnakeCollision(snake1, snake2)) {
        gameOver = true;
        aiScore += 1;
        updateScores();
        const message = document.createElement('div');
        message.textContent = "Snake 2 (AI) Wins!";
        message.style.position = 'absolute';
        message.style.top = '50%';
        message.style.left = '50%';
        message.style.whiteSpace = 'nowrap';
        message.style.transform = 'translate(-50%, -50%)';
        message.style.fontSize = '10em';
        message.style.color = 'white';
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

    if (checkCollision(snake2) || checkSnakeCollision(snake2, snake1)) {
        gameOver = true;
        playerScore += 1;
        updateScores();
        const message = document.createElement('div');
        message.textContent = "Snake 1 Wins!!";
        message.style.position = 'absolute';
        message.style.top = '50%';
        message.style.left = '50%';
        message.style.whiteSpace = 'nowrap';
        message.style.transform = 'translate(-50%, -50%)';
        message.style.fontSize = '10em';
        message.style.color = 'white';
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

    if (snake1[0].x === food.x && snake1[0].y === food.y) {
        growSnake(snake1);
        food = spawnFood();
    }

    if (snake2[0].x === food.x && snake2[0].y === food.y) {
        growSnake(snake2);
        food = spawnFood();
    }
}

// AI-controlled snake movement
function aiMove() {
    let bestMove = { x: direction2.x, y: direction2.y };
    let minDistance = Infinity;

    const possibleMoves = [
        { x: 0, y: -1 }, // Up
        { x: 0, y: 1 },  // Down
        { x: -1, y: 0 }, // Left
        { x: 1, y: 0 }   // Right
    ];

    possibleMoves.forEach(move => {
        let newX = snake2[0].x + move.x;
        let newY = snake2[0].y + move.y;
        let distance = Math.abs(newX - food.x) + Math.abs(newY - food.y);
        if (distance < minDistance && !checkCollision([{ x: newX, y: newY }])) {
            bestMove = move;
            minDistance = distance;
        }
    });

    direction2 = bestMove;
    moveSnake(snake2, direction2);
}

// Draw the game
function draw() {
    ctx.fillStyle = 'black';
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    ctx.fillStyle = 'red';
    ctx.fillRect(food.x * CELL_SIZE, food.y * CELL_SIZE, CELL_SIZE, CELL_SIZE);

    ctx.fillStyle = 'green';
    snake1.forEach(segment => {
        ctx.fillRect(segment.x * CELL_SIZE, segment.y * CELL_SIZE, CELL_SIZE, CELL_SIZE);
    });

    ctx.fillStyle = 'blue';
    snake2.forEach(segment => {
        ctx.fillRect(segment.x * CELL_SIZE, segment.y * CELL_SIZE, CELL_SIZE, CELL_SIZE);
    });
}

// Move the snake
function moveSnake(snake, direction) {
    const head = { x: snake[0].x + direction.x, y: snake[0].y + direction.y };
    snake.unshift(head);
    snake.pop();
}

// Grow the snake
function growSnake(snake) {
    const tail = snake[snake.length - 1];
    snake.push({ x: tail.x, y: tail.y });
}

// Check collision with walls
function checkCollision(snake) {
    const head = snake[0];
    return head.x < 0 || head.x >= COLS || head.y < 0 || head.y >= ROWS;
}

// Check collision with another snake
function checkSnakeCollision(snake1, snake2) {
    const head = snake1[0];
    return snake2.some(segment => segment.x === head.x && segment.y === head.y);
}

// Spawn food
function spawnFood() {
    let x, y;
    do {
        x = Math.floor(Math.random() * COLS);
        y = Math.floor(Math.random() * ROWS);
    } while (
        snake1.some(segment => segment.x === x && segment.y === y) ||
        snake2.some(segment => segment.x === x && segment.y === y)
    );
    return { x, y };
}

// Handle key presses
window.addEventListener('keydown', e => {
    switch (e.key) {
        // Snake arrow controls
        case 'ArrowUp':
            if (direction1.y === 0) direction1 = { x: 0, y: -1 };
            break;
        case 'ArrowDown':
            if (direction1.y === 0) direction1 = { x: 0, y: 1 };
            break;
        case 'ArrowLeft':
            if (direction1.x === 0) direction1 = { x: -1, y: 0 };
            break;
        case 'ArrowRight':
            if (direction1.x === 0) direction1 = { x: 1, y: 0 };
            break;

        // Snake letter controls
        case 'w':
            if (direction1.y === 0) direction1 = { x: 0, y: -1 };
            break;
        case 's':
            if (direction1.y === 0) direction1 = { x: 0, y: 1 };
            break;
        case 'a':
            if (direction1.x === 0) direction1 = { x: -1, y: 0 };
            break;
        case 'd':
            if (direction1.x === 0) direction1 = { x: 1, y: 0 };
            break;
    }
});

// Start the game
gameLoop();

// Reset the game
document.getElementById('resetButton').addEventListener('click', () => {
    resetGame()
});

function resetGame() {
    gameOver = false;
    snake1 = [{ x: 10, y: 10 }];
    snake2 = [{ x: 20, y: 20 }];
    direction1 = { x: 1, y: 0 };
    direction2 = { x: -1, y: 0 };
    score1 = 0;
    score2 = 0;
    food = spawnFood();
    gameLoop();
}

// Update scores in the UI
function updateScores() {
    playerScoreElement.textContent = playerScore;
    aiScoreElement.textContent = aiScore;
}