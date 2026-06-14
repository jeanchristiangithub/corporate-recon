<?php

declare(strict_types=1);

require_once __DIR__ . '/usercontroller.php';

class ChangePassController
{
    private UserController $userController;

    public function __construct()
    {
        $this->userController = new UserController();
    }

    public function changePassword(string $idNumber, string $newPassword): bool
    {
        $newPassword = trim($newPassword);

        if (strlen($newPassword) < 8) {
            return false;
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        return $this->userController->updatePasswordAndMarkLog($idNumber, $hash);
    }
}
