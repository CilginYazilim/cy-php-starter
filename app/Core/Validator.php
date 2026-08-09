<?php
/**
 * =====================================================================
 *  Validator – Form doğrulama
 * ---------------------------------------------------------------------
 *  ALTIN KURAL: JavaScript tarafındaki doğrulama sadece KULLANICI
 *  DENEYİMİ içindir. Her kontrol SUNUCUDA TEKRARLANIR.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    /** @var array<string,string> */
    private array $errors = [];

    /** @var array<string,mixed> */
    private array $clean = [];

    /** @param array<string,mixed> $data */
    public function __construct(private array $data)
    {
    }

    /** Ad / soyad: harf, boşluk, nokta, kesme işareti, tire. */
    public function name(string $field, string $label): self
    {
        $value = $this->normalizeSpaces($this->get($field));
        $min   = (int) Config::get('validation.name_min', 2);
        $max   = (int) Config::get('validation.name_max', 100);

        if (!mb_check_encoding($value, 'UTF-8')) {
            return $this->fail($field, $label . ' geçersiz karakterler içeriyor.');
        }

        if ($value === '') {
            return $this->fail($field, $label . ' alanı boş bırakılamaz.');
        }

        $length = mb_strlen($value, 'UTF-8');

        if ($length < $min) {
            return $this->fail($field, $label . ' en az ' . $min . ' karakter olmalıdır.');
        }
        if ($length > $max) {
            return $this->fail($field, $label . ' en fazla ' . $max . ' karakter olabilir.');
        }
        if (!preg_match("/^[\p{L}\p{M}\s.'-]+$/u", $value)) {
            return $this->fail($field, $label . ' yalnızca harf, boşluk, nokta, kesme işareti ve tire içerebilir.');
        }

        $this->clean[$field] = $value;

        return $this;
    }

    /** Serbest metin (uzunluk sınırlı, harf kısıtı yok). */
    public function text(string $field, string $label, int $min = 0, int $max = 500, bool $required = false): self
    {
        $value = trim($this->get($field));

        if (!mb_check_encoding($value, 'UTF-8')) {
            return $this->fail($field, $label . ' geçersiz karakterler içeriyor.');
        }

        if ($value === '') {
            if ($required) {
                return $this->fail($field, $label . ' alanı boş bırakılamaz.');
            }
            $this->clean[$field] = '';
            return $this;
        }

        $length = mb_strlen($value, 'UTF-8');

        if ($length < $min) {
            return $this->fail($field, $label . ' en az ' . $min . ' karakter olmalıdır.');
        }
        if ($length > $max) {
            return $this->fail($field, $label . ' en fazla ' . $max . ' karakter olabilir.');
        }

        $this->clean[$field] = $value;

        return $this;
    }

    /** Kullanıcı adı: harf(EN), rakam, nokta, alt çizgi. */
    public function username(string $field = 'kullanici_adi', string $label = 'Kullanıcı adı'): self
    {
        $value = trim($this->get($field));

        if ($value === '') {
            return $this->fail($field, $label . ' boş bırakılamaz.');
        }
        if (mb_strlen($value, 'UTF-8') < 3) {
            return $this->fail($field, $label . ' en az 3 karakter olmalıdır.');
        }
        if (mb_strlen($value, 'UTF-8') > 50) {
            return $this->fail($field, $label . ' en fazla 50 karakter olabilir.');
        }
        if (!preg_match('/^[a-zA-Z0-9._]+$/', $value)) {
            return $this->fail($field, $label . ' yalnızca İngilizce harf, rakam, nokta ve alt çizgi içerebilir.');
        }

        $this->clean[$field] = $value;

        return $this;
    }

    public function email(string $field = 'eposta', string $label = 'E-posta'): self
    {
        $value = mb_strtolower(trim($this->get($field)), 'UTF-8');

        if ($value === '') {
            return $this->fail($field, $label . ' alanı boş bırakılamaz.');
        }
        if (mb_strlen($value) > 190) {
            return $this->fail($field, $label . ' en fazla 190 karakter olabilir.');
        }
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return $this->fail($field, 'Geçerli bir e-posta adresi giriniz.');
        }

        $this->clean[$field] = $value;

        return $this;
    }

    public function password(string $field = 'sifre', bool $required = true, ?string $confirmField = null): self
    {
        $value = (string) ($this->data[$field] ?? '');
        $min   = (int) Config::get('security.password_min', 8);

        if ($value === '') {
            if ($required) {
                return $this->fail($field, 'Parola alanı boş bırakılamaz.');
            }
            return $this;
        }

        if (mb_strlen($value) < $min) {
            return $this->fail($field, 'Parola en az ' . $min . ' karakter olmalıdır.');
        }
        if (strlen($value) > 72) {
            return $this->fail($field, 'Parola en fazla 72 karakter olabilir.');
        }
        if (!preg_match('/[\p{L}]/u', $value) || !preg_match('/\d/', $value)) {
            return $this->fail($field, 'Parola en az bir harf ve bir rakam içermelidir.');
        }
        if ($confirmField !== null && (string) ($this->data[$confirmField] ?? '') !== $value) {
            return $this->fail($confirmField, 'Parolalar birbiriyle eşleşmiyor.');
        }

        $this->clean[$field] = $value;

        return $this;
    }

    /** @param array<int,string> $allowed */
    public function in(string $field, array $allowed, string $label): self
    {
        $value = $this->get($field);

        if (!in_array($value, $allowed, true)) {
            return $this->fail($field, $label . ' için geçersiz bir değer seçildi.');
        }

        $this->clean[$field] = $value;

        return $this;
    }

    public function phone(string $field = 'telefon', string $label = 'Telefon'): self
    {
        $value = trim($this->get($field));

        if ($value === '') {
            $this->clean[$field] = '';
            return $this;
        }
        if (!preg_match('/^[0-9+()\s-]{7,25}$/', $value)) {
            return $this->fail($field, $label . ' geçerli bir biçimde değil.');
        }

        $this->clean[$field] = $value;

        return $this;
    }

    public function fails(): bool  { return $this->errors !== []; }
    public function passes(): bool { return $this->errors === []; }

    /** @return array<string,string> */
    public function errors(): array { return $this->errors; }

    /** @return array<string,mixed> */
    public function validated(): array { return $this->clean; }

    public function addError(string $field, string $message): self
    {
        return $this->fail($field, $message);
    }

    private function get(string $field): string
    {
        $value = $this->data[$field] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }

    private function normalizeSpaces(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function fail(string $field, string $message): self
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }

        return $this;
    }
}
