<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Fragment\Reference;

use Contao\CoreBundle\Fragment\Reference\FrontendModuleReference;
use Contao\CoreBundle\Tests\TestCase;
use Contao\ModuleModel;
use PHPUnit\Framework\MockObject\MockObject;

class FrontendModuleReferenceTest extends TestCase
{
    public function testCreatesTheControllerNameFromTheModelType(): void
    {
        /** @var ModuleModel&MockObject $model */
        $model = $this->mockClassWithProperties(ModuleModel::class);
        $model->type = 'foobar';

        $reference = new FrontendModuleReference($model);

        $this->assertSame(FrontendModuleReference::TAG_NAME.'.foobar', $reference->controller);
    }

    public function testAddsTheSectionAttribute(): void
    {
        $model = $this->createMock(ModuleModel::class);

        $reference = new FrontendModuleReference($model);
        $this->assertSame('main', $reference->attributes['section']);

        $reference = new FrontendModuleReference($model, 'header');
        $this->assertSame('header', $reference->attributes['section']);

        $reference = new FrontendModuleReference($model, 'footer');
        $this->assertSame('footer', $reference->attributes['section']);
    }
}
