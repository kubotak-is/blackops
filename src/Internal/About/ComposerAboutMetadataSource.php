<?php

declare(strict_types=1);

namespace BlackOps\Internal\About;

use Closure;
use Composer\InstalledVersions;

final readonly class ComposerAboutMetadataSource implements AboutMetadataSource
{
    /** @var Closure(): mixed */
    private Closure $versionLookup;

    public function __construct(
        ?Closure $versionLookup = null,
        private string $phpVersion = PHP_VERSION,
        private string $sapi = PHP_SAPI,
    ) {
        /** @mago-ignore analysis:non-existent-method */
        $this->versionLookup =
            $versionLookup ?? static fn(): mixed => InstalledVersions::getPrettyVersion('blackops/framework');
    }

    public function read(): AboutMetadata
    {
        $version = ($this->versionLookup)();
        if (!is_string($version)) {
            throw new \InvalidArgumentException('Composer installed package version is invalid.');
        }

        return new AboutMetadata(version: $version, phpVersion: $this->phpVersion, sapi: $this->sapi);
    }
}
