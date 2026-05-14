<?php

declare(strict_types=1);

/**
 * CsrfManager.php - Cross-Site Request Forgery Protection
 *
 * Generates and validates CSRF tokens to protect forms from
 * cross-site request forgery attacks. Tokens are stored in the
 * PHP session and tied to a single request lifecycle.
 *
 * @package RedWolf\MagentoLite\Security
 * @version 1.0.0
 */

namespace RedWolf\MagentoLite\Security;

use RuntimeException;

class CsrfManager
{
    /** @var string Session key for storing the CSRF token */
    private const TOKEN_SESSION_KEY = 'redwolf_csrf_token';

    /** @var string Session key for storing token generation time */
    private const TOKEN_TIME_KEY = 'redwolf_csrf_time';

    /** @var int Token expiry time in seconds (1 hour) */
    private const TOKEN_TTL = 3600;

    /** @var int Token length in bytes (hex-encoded to 64 characters) */
    private const TOKEN_BYTES = 32;

    /**
     * Generates a new CSRF token and stores it in the session.
     * Uses random_bytes() for cryptographically secure generation.
     *
     * @return string The generated token
     * @throws RuntimeException If session is not available or random generation fails
     */
    public function generateToken(): string
    {
        $this->ensureSession();

        try {
            $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        } catch (\Exception $e) {
            throw new RuntimeException(
                'Failed to generate CSRF token: ' . $e->getMessage(),
                0,
                $e
            );
        }

        $_SESSION[self::TOKEN_SESSION_KEY] = $token;
        $_SESSION[self::TOKEN_TIME_KEY] = time();

        return $token;
    }

    /**
     * Validates a provided CSRF token against the session-stored token.
     * After successful validation, the token is consumed (single-use).
     * Also checks token expiry to prevent replay attacks.
     *
     * @param string $token The token to validate
     * @return bool True if the token is valid and not expired
     */
    public function validateToken(string $token): bool
    {
        $this->ensureSession();

        $storedToken = $_SESSION[self::TOKEN_SESSION_KEY] ?? '';
        $generatedAt = $_SESSION[self::TOKEN_TIME_KEY] ?? 0;

        // Token must match and not be expired
        $isValid = hash_equals($storedToken, $token)
            && (time() - $generatedAt) < self::TOKEN_TTL;

        // Consume the token after validation (single-use)
        unset(
            $_SESSION[self::TOKEN_SESSION_KEY],
            $_SESSION[self::TOKEN_TIME_KEY]
        );

        return $isValid;
    }

    /**
     * Returns an HTML hidden input field containing the CSRF token.
     * Generates a new token if one does not exist.
     *
     * @return string HTML hidden input element
     */
    public function getFormField(): string
    {
        $token = $this->getActiveToken();

        return '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Returns an HTML hidden input field with a custom name attribute.
     *
     * @param string $fieldName The name attribute for the input field
     * @return string HTML hidden input element
     */
    public function getFormFieldWithName(string $fieldName): string
    {
        $token = $this->getActiveToken();

        return '<input type="hidden" name="' . htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8')
            . '" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Returns the current active token, generating one if needed.
     *
     * @return string The active CSRF token
     */
    public function getActiveToken(): string
    {
        $this->ensureSession();

        $token = $_SESSION[self::TOKEN_SESSION_KEY] ?? '';

        if ($token === '') {
            $token = $this->generateToken();
        }

        return $token;
    }

    /**
     * Ensures that a PHP session is active.
     *
     * @return void
     * @throws RuntimeException If session cannot be started
     */
    private function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            if (!session_start()) {
                throw new RuntimeException('Failed to start session for CSRF protection.');
            }
        }
    }
}
