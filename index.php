<?php
require 'vendor/autoload.php';

use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\GraphQL;
use GraphQL\Type\Schema;

// Define a User Type
$userType = new ObjectType([
    'name' => 'User',
    'fields' => [
        'id' => Type::int(),
        'name' => Type::string(),
        'email' => Type::string(),
    ],
]);

// Define Query Type with Users Data
$queryType = new ObjectType([
    'name' => 'Query',
    'fields' => [
        'hello' => [
            'type' => Type::string(),
            'resolve' => fn() => 'Hello, GraphQL with PHP!'
        ],
        'user' => [
            'type' => $userType,
            'args' => [
                'id' => Type::int()
            ],
            'resolve' => function ($root, $args) {
                // Mock database users
                $users = [
                    1 => ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'],
                    2 => ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com'],
                ];
                return $users[$args['id']] ?? null;
            }
        ],
        'users' => [
            'type' => Type::listOf($userType),
            'resolve' => fn() => [
                ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'],
                ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com'],
            ],
        ],
    ],
]);

// Define Schema
$schema = new Schema([
    'query' => $queryType,
]);

// Manually Define Raw Input with More Data
$rawInput = json_encode([
    'query' => '{
        hello
        user(id: 1) {
            id
            name
            email
        }
        users {
            id
            name
            email
        }
    }'
]);

// Decode JSON Input (Simulating an Actual Request)
$input = json_decode($rawInput, true);
$query = $input['query'] ?? '';

// Execute Query
try {
    $result = GraphQL::executeQuery($schema, $query);
    echo json_encode($result->toArray(), JSON_PRETTY_PRINT);
} catch (\Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
