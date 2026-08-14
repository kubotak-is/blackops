<?php

declare(strict_types=1);

namespace BlackOps\Tests\Fixtures\Aop\FrameworkProxyCompatibility;

use BlackOps\Database\Attribute\Transactional;

#[Transactional(connection: 'app')]
class InheritedSignatureService extends InheritedSignatureParent {}
