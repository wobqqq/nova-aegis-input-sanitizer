<?php

declare(strict_types=1);

namespace Wobqqq\AegisInputSanitizer\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Override;

/**
 * @property int $id
 * @property string $email
 * @property bool $is_admin
 */
final class User extends Authenticatable
{
    /** @var array<string> */
    protected $guarded = [];

    #[Override]
    protected function casts(): array
    {
        return ['is_admin' => 'boolean'];
    }
}
