<?php
ini_set('display_errors', '0');
require 'vendor/autoload.php';
require 'connect.php';

use GraphQL\GraphQL;
use GraphQL\Type\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ResolveInfo;

$userType = new ObjectType([
    'name' => 'User',
    'fields' => [
        'id' => Type::int(),
        'name' => Type::string(),
        'email' => Type::string(),
    ],
]);

$queryType = new ObjectType([
    'name' => 'Query',
    'fields' => [
        'users' => [
            'type' => Type::listOf($userType),
            'resolve' => function () use ($pdo) {
                $stmt = $pdo->query("SELECT id, name, email FROM users");
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        ]
    ]
]);

// $queryType = new ObjectType([
//     'name' => 'Query',
//     'fields' => [
//         'users' => [
//             'type' => Type::listOf($userType),
//             'resolve' => function ($root, $args, $context, ResolveInfo $info) use ($pdo) {
//                 // Get requested field names
//                 $requestedFields = array_keys($info->getFieldSelection());

//                 // Sanitize fields to prevent SQL injection (ensure they match actual DB columns)
//                 $validFields = ['id', 'name', 'email'];
//                 $columns = implode(', ', array_intersect($requestedFields, $validFields));

//                 // Fallback to all if none matched
//                 if (empty($columns)) {
//                     $columns = implode(', ', $validFields);
//                 }

//                 // echo "SELECT $columns FROM users";

//                 $stmt = $pdo->query("SELECT $columns FROM users");
//                 return $stmt->fetchAll(PDO::FETCH_ASSOC);
//             }
//         ]
//     ]
// ]);

$mutationType = new ObjectType([
    'name' => 'Mutation',
    'fields' => [
        'createUser' => [
            'type' => $userType, // Return the created user
            'args' => [
                'name' => Type::nonNull(Type::string()),
                'email' => Type::nonNull(Type::string())
            ],
            'resolve' => function ($root, $args) use ($pdo) {
                $stmt = $pdo->prepare("INSERT INTO users (name, email) VALUES (?, ?)");
                $stmt->execute([$args['name'], $args['email']]);

                $id = $pdo->lastInsertId();
                return [
                    'id' => (int)$id,
                    'name' => $args['name'],
                    'email' => $args['email']
                ];
            }
        ],
        'updateUser' => [
            'type' => $userType, // Return updated user
            'args' => [
                'id' => Type::nonNull(Type::int()),
                'name' => Type::string(),
                'email' => Type::string()
            ],
            'resolve' => function ($root, $args) use ($pdo) {
                // Prepare dynamic fields
                $updates = [];
                $values = [];

                if (isset($args['name'])) {
                    $updates[] = 'name = ?';
                    $values[] = $args['name'];
                }

                if (isset($args['email'])) {
                    $updates[] = 'email = ?';
                    $values[] = $args['email'];
                }

                if (empty($updates)) {
                    throw new \Exception('No fields provided to update');
                }

                $values[] = $args['id']; // Bind ID last
                $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);

                // Return updated record
                $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ?");
                $stmt->execute([$args['id']]);
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
        ],
        'deleteUser' => [
            'type' => Type::boolean(), // Return true if deleted
            'args' => [
                'id' => Type::nonNull(Type::int())
            ],
            'resolve' => function ($root, $args) use ($pdo) {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$args['id']]);

                return $stmt->rowCount() > 0;
            }
        ]
    ]
]);

$schema = new Schema([
    'query' => $queryType,
    'mutation' => $mutationType
]);

try {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    $query = $input['query'];

    $result = GraphQL::executeQuery($schema, $query);
    $output = $result->toArray();
} catch (\Exception $e) {
    $output = ['error' => $e->getMessage()];
}

header('Content-Type: application/json');
echo json_encode($output);
