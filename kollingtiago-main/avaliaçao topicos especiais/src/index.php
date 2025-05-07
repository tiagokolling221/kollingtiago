<?php

header("Content-Type: text/plain");

// Conexão com o banco MySQL
$host = 'mysql';
$user = 'usuario_livros';
$pass = 'senha_livros';
$db   = 'meu_banco';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    http_response_code(500);
    die("Erro na conexão: " . $conn->connect_error);
}

// Criação da tabela se não existir
$conn->query("
    CREATE TABLE IF NOT EXISTS livros (
        id VARCHAR(32) PRIMARY KEY,
        titulo VARCHAR(255) NOT NULL,
        autor VARCHAR(255) NOT NULL,
        ano INT NOT NULL
    )
");

// Inserção de dados de exemplo, se a tabela estiver vazia
$result = $conn->query("SELECT COUNT(*) as total FROM livros");
$count = $result->fetch_assoc()['total'];
if ($count == 0) {
    $conn->query("INSERT INTO livros (id, titulo, autor, ano) VALUES
        ('" . uniqid() . "', 'Dom Casmurro', 'Machado de Assis', 1899),
        ('" . uniqid() . "', '1984', 'George Orwell', 1949),
        ('" . uniqid() . "', 'O Pequeno Príncipe', 'Antoine de Saint-Exupéry', 1943)
    ");
}

// Funções da API
function listarLivros($conn) {
    $result = $conn->query("SELECT * FROM livros");
    while ($row = $result->fetch_assoc()) {
        echo "ID: {$row['id']} | Título: {$row['titulo']} | Autor: {$row['autor']} | Ano: {$row['ano']}\n";
    }
}

function buscarLivro($conn, $id) {
    $stmt = $conn->prepare("SELECT * FROM livros WHERE id = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($livro = $res->fetch_assoc()) {
        echo "ID: {$livro['id']}\nTítulo: {$livro['titulo']}\nAutor: {$livro['autor']}\nAno: {$livro['ano']}";
    } else {
        http_response_code(404);
        echo "Livro não encontrado";
    }
}

function adicionarLivro($conn) {
    parse_str(file_get_contents("php://input"), $input);
    if (!isset($input['titulo'], $input['autor'], $input['ano'])) {
        http_response_code(400);
        echo "Dados inválidos. Envie 'titulo', 'autor' e 'ano'.";
        return;
    }

    $id = uniqid();
    $stmt = $conn->prepare("INSERT INTO livros (id, titulo, autor, ano) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $id, $input['titulo'], $input['autor'], $input['ano']);
    $stmt->execute();
    echo "Livro adicionado com ID: $id";
}

function atualizarLivro($conn, $id) {
    parse_str(file_get_contents("php://input"), $input);
    if (!isset($input['titulo'], $input['autor'], $input['ano'])) {
        http_response_code(400);
        echo "Dados inválidos. Envie 'titulo', 'autor' e 'ano'.";
        return;
    }

    $stmt = $conn->prepare("UPDATE livros SET titulo = ?, autor = ?, ano = ? WHERE id = ?");
    $stmt->bind_param("ssis", $input['titulo'], $input['autor'], $input['ano'], $id);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        echo "Livro atualizado com sucesso.";
    } else {
        http_response_code(404);
        echo "Livro não encontrado.";
    }
}

function removerLivro($conn, $id) {
    $stmt = $conn->prepare("DELETE FROM livros WHERE id = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        echo "Livro removido com sucesso.";
    } else {
        http_response_code(404);
        echo "Livro não encontrado.";
    }
}

// Roteamento
$method = $_SERVER['REQUEST_METHOD'];
$requestUri = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
$scriptName = explode('/', trim($_SERVER['SCRIPT_NAME'], '/'));
$apiPath = array_slice($requestUri, count($scriptName));

if ($method === 'GET' && count($apiPath) === 1 && $apiPath[0] === 'livros') {
    listarLivros($conn);
} elseif ($method === 'POST' && count($apiPath) === 1 && $apiPath[0] === 'livros') {
    adicionarLivro($conn);
} elseif ($method === 'GET' && count($apiPath) === 2 && $apiPath[0] === 'livros') {
    buscarLivro($conn, $apiPath[1]);
} elseif ($method === 'PUT' && count($apiPath) === 2 && $apiPath[0] === 'livros') {
    atualizarLivro($conn, $apiPath[1]);
} elseif ($method === 'DELETE' && count($apiPath) === 2 && $apiPath[0] === 'livros') {
    removerLivro($conn, $apiPath[1]);
} else {
    http_response_code(404);
    echo "Endpoint não encontrado.";
}

$conn->close();
