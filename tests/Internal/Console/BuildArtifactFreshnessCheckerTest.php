<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Http\Routing\HttpOperationManifest;
use BlackOps\Http\Routing\HttpOperationManifestFile;
use BlackOps\Internal\Build\BuildArtifactFingerprintGuard;
use BlackOps\Internal\Console\BuildArtifactFreshnessChecker;
use BlackOps\Internal\Frontend\FrontendContractManifest;
use BlackOps\Internal\Frontend\FrontendContractManifestFile;
use BlackOps\Internal\Registry\OperationManifestFile;
use PHPUnit\Framework\TestCase;

final class BuildArtifactFreshnessCheckerTest extends TestCase
{
    public function testRequiresAllManifestBuildIdsAndSchemasToBeFresh(): void
    {
        $directory = sys_get_temp_dir() . '/blackops-frontend-freshness-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $input = $directory . '/input.php';
        $fingerprint = $directory . '/fingerprint.php';
        $operation = $directory . '/operations.php';
        $http = $directory . '/http.php';
        $frontend = $directory . '/frontend.php';
        $container = $directory . '/container.php';
        file_put_contents($input, '<?php return [];');
        file_put_contents($container, '<?php return true;');
        new OperationManifestFile()->write(new OperationRegistry([]), $operation, 'fresh-build');
        new HttpOperationManifestFile()->write(new HttpOperationManifest([], [], [[], []]), $http, 'fresh-build');
        new FrontendContractManifestFile()->write(new FrontendContractManifest([]), $frontend, 'fresh-build');
        new BuildArtifactFingerprintGuard()->update($fingerprint, [$input]);

        $checker = new BuildArtifactFreshnessChecker();
        $outputs = [$operation, $http, $frontend, $container];
        $manifests = ['operation' => $operation, 'http' => $http, 'frontend' => $frontend];
        self::assertTrue($checker->isFresh($fingerprint, [$input], $outputs, $manifests, 'fresh-build'));

        $frontendSource = (string) file_get_contents($frontend);
        $legacySource = str_replace("'schemaVersion' => 2,", "'schemaVersion' => 1,", $frontendSource);
        self::assertNotSame($frontendSource, $legacySource);
        file_put_contents($frontend, $legacySource);
        self::assertFalse($checker->isFresh($fingerprint, [$input], $outputs, $manifests, 'fresh-build'));

        new FrontendContractManifestFile()->write(new FrontendContractManifest([]), $frontend, 'stale-build');
        self::assertFalse($checker->isFresh($fingerprint, [$input], $outputs, $manifests, 'fresh-build'));
    }
}
