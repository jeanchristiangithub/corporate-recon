<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

class UserController
{
    private PDO $userConn;

    public function __construct()
    {
        $this->userConn = userDbConnection();
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->userConn->prepare(
            "SELECT `no`, id_number, username, firstname, middlename, lastname, role, password, dateCreated 
             FROM users 
             WHERE username = :username"
        );

        $stmt->bindValue(':username', $username, PDO::PARAM_STR);
        $stmt->execute();

        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function latestUserLogByIdNumber(string $idNumber): ?array
    {
        $stmt = $this->userConn->prepare(
            'SELECT datemodified, status FROM userlogs WHERE id_number = :id_number ORDER BY datemodified DESC LIMIT 1'
        );
        $stmt->bindValue(':id_number', $idNumber, PDO::PARAM_STR);
        $stmt->execute();

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function markLoginAndEnsureLog(string $idNumber): bool
    {
        try {
            $update = $this->userConn->prepare(
                'UPDATE userlogs SET datemodified = NOW(), status = :status WHERE id_number = :id_number ORDER BY datemodified DESC LIMIT 1'
            );
            $update->bindValue(':status', 'active', PDO::PARAM_STR);
            $update->bindValue(':id_number', $idNumber, PDO::PARAM_STR);
            $update->execute();

            if ($update->rowCount() > 0) {
                return true;
            }

            $insert = $this->userConn->prepare(
                'INSERT INTO userlogs (id_number, datemodified, status) VALUES (:id_number, NOW(), :status)'
            );
            $insert->bindValue(':id_number', $idNumber, PDO::PARAM_STR);
            $insert->bindValue(':status', 'active', PDO::PARAM_STR);
            return $insert->execute();
        } catch (Throwable $e) {
            return false;
        }
    }

    public function updatePasswordAndMarkLog(string $idNumber, string $passwordHash): bool
    {
        $this->userConn->beginTransaction();

        try {
            $updateUser = $this->userConn->prepare('UPDATE users SET password = :password WHERE id_number = :id_number');
            $updateUser->bindValue(':password', $passwordHash, PDO::PARAM_STR);
            $updateUser->bindValue(':id_number', $idNumber, PDO::PARAM_STR);
            $updateUser->execute();

            $updateLog = $this->userConn->prepare(
                'UPDATE userlogs SET datemodified = NOW(), status = :status WHERE id_number = :id_number ORDER BY datemodified DESC LIMIT 1'
            );
            $updateLog->bindValue(':status', 'active', PDO::PARAM_STR);
            $updateLog->bindValue(':id_number', $idNumber, PDO::PARAM_STR);
            $updateLog->execute();

            $this->userConn->commit();
            return true;
        } catch (Throwable $exception) {
            if ($this->userConn->inTransaction()) {
                $this->userConn->rollBack();
            }

            return false;
        }
    }

    public function userExistsByUsername(string $username): bool
    {
        $stmt = $this->userConn->prepare('SELECT 1 FROM users WHERE username = :username LIMIT 1');
        $stmt->bindValue(':username', $username, PDO::PARAM_STR);
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }

    public function userExistsByIdNumber(string $idNumber): bool
    {
        $stmt = $this->userConn->prepare('SELECT 1 FROM users WHERE id_number = :id_number LIMIT 1');
        $stmt->bindValue(':id_number', $idNumber, PDO::PARAM_STR);
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }

    public function findByIdNumber(string $idNumber): ?array
    {
        $stmt = $this->userConn->prepare(
            "SELECT `no`, id_number, username, firstname, middlename, lastname, role, password, dateCreated 
             FROM users 
             WHERE id_number = :id_number"
        );

        $stmt->bindValue(':id_number', $idNumber, PDO::PARAM_STR);
        $stmt->execute();

        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function createUser(array $data): bool
    {
        // Expected keys: id_number, username, firstname, lastname, role, password_hash
        $this->userConn->beginTransaction();
        try {
            $check = $this->userConn->prepare('SELECT 1 FROM users WHERE username = :username OR id_number = :id_number LIMIT 1');
            $check->bindValue(':username', $data['username'], PDO::PARAM_STR);
            $check->bindValue(':id_number', $data['id_number'], PDO::PARAM_STR);
            $check->execute();
            if ($check->fetch()) {
                if ($this->userConn->inTransaction()) $this->userConn->rollBack();
                return false;
            }

            $insert = $this->userConn->prepare(
                'INSERT INTO users (id_number, username, firstname, middlename, lastname, role, password, dateCreated) VALUES (:id_number, :username, :firstname, :middlename, :lastname, :role, :password, NOW())'
            );
            $insert->bindValue(':id_number', $data['id_number'], PDO::PARAM_STR);
            $insert->bindValue(':username', $data['username'], PDO::PARAM_STR);
            $insert->bindValue(':firstname', $data['firstname'] ?? '', PDO::PARAM_STR);
            $insert->bindValue(':middlename', $data['middlename'] ?? '', PDO::PARAM_STR);
            $insert->bindValue(':lastname', $data['lastname'] ?? '', PDO::PARAM_STR);
            $insert->bindValue(':role', $data['role'] ?? 'Public', PDO::PARAM_STR);
            $insert->bindValue(':password', $data['password_hash'], PDO::PARAM_STR);
            $insert->execute();

            $this->userConn->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->userConn->inTransaction()) $this->userConn->rollBack();
            return false;
        }
    }

    public function getAllUsers(): array
    {
        $stmt = $this->userConn->prepare(
            'SELECT id_number, username, firstname, middlename, lastname, role, dateCreated FROM users ORDER BY dateCreated DESC'
        );
        $stmt->execute();
        $rows = $stmt->fetchAll();
        return $rows ?: [];
    }

    public function deleteUserByIdNumber(string $idNumber): bool
    {
        try {
            $stmt = $this->userConn->prepare('DELETE FROM users WHERE id_number = :id_number');
            $stmt->bindValue(':id_number', $idNumber, PDO::PARAM_STR);
            return $stmt->execute();
        } catch (Throwable $e) {
            return false;
        }
    }
}
