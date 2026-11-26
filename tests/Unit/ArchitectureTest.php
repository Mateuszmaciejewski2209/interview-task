<?php

declare(strict_types=1);

use Modules\Shared\Domain\BusinessRule;

arch('the invoice domain is framework-free and self-contained')
    ->expect('Modules\Invoices\Domain')
    ->not->toUse([
        'Illuminate',
        'Modules\Invoices\Application',
        'Modules\Invoices\Infrastructure',
        'Modules\Notifications',
    ]);

arch('the invoice application reaches the outside only through its ports')
    ->expect('Modules\Invoices\Application')
    ->not->toUse(['Illuminate', 'Modules\Invoices\Infrastructure', 'Modules\Notifications']);

arch('invoices use only the public API of the Notifications module')
    ->expect('Modules\Invoices')
    ->not->toUse([
        'Modules\Notifications\Application',
        'Modules\Notifications\Infrastructure',
        'Modules\Notifications\Presentation',
    ]);

arch('notifications know nothing about invoices')
    ->expect('Modules\Notifications')
    ->not->toUse('Modules\Invoices');

arch('the shared kernel is framework-free outside its infrastructure')
    ->expect(['Modules\Shared\Domain', 'Modules\Shared\Application'])
    ->not->toUse('Illuminate');

arch('business rules are named, immutable conditions')
    ->expect('Modules\Invoices\Domain\Rules')
    ->toImplement(BusinessRule::class)
    ->toBeFinal()
    ->toBeReadonly();

// separate arch() because ignoring() only applies to the expectation before it
arch('commands are named as commands')
    ->expect('Modules\Invoices\Application\Commands')
    ->toHaveSuffix('Command')
    ->ignoring('Modules\Invoices\Application\Commands\Handlers');

arch('commands and their handlers are immutable')
    ->expect('Modules\Invoices\Application\Commands')
    ->toBeFinal()
    ->toBeReadonly();

arch('each command and query has one invokable handler')
    ->expect(['Modules\Invoices\Application\Commands\Handlers', 'Modules\Invoices\Application\Queries\Handlers'])
    ->toHaveSuffix('Handler')
    ->toBeFinal()
    ->toBeReadonly()
    ->toBeInvokable();

arch('the application talks to the outside through ports')
    ->expect('Modules\Invoices\Application\Ports')
    ->toBeInterfaces();

arch('no debugging leftovers')
    ->expect(['Modules\Invoices', 'Modules\Shared'])
    ->not->toUse(['dd', 'dump', 'var_dump', 'ray']);
