<?php

declare(strict_types=1);

/*
 * This file is part of the overtrue/phplint package
 *
 * (c) overtrue
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Overtrue\PHPLint\Output;

use Bartlett\Sarif\Definition\ArtifactLocation;
use Bartlett\Sarif\Definition\Location;
use Bartlett\Sarif\Definition\Message;
use Bartlett\Sarif\Definition\PhysicalLocation;
use Bartlett\Sarif\Definition\Region;
use Bartlett\Sarif\Definition\Result;
use Bartlett\Sarif\Definition\Run;
use Bartlett\Sarif\Definition\Tool;
use Bartlett\Sarif\Definition\ToolComponent;
use Bartlett\Sarif\SarifLog;
use Symfony\Component\Console\Output\StreamOutput;

use function fclose;
use function getcwd;
use function json_encode;
use function parse_url;
use function str_replace;
use function strlen;
use function substr;
use const DIRECTORY_SEPARATOR;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const PHP_URL_SCHEME;

/**
 * @author Laurent Laville
 * @since Release 9.1.0
 */
class SarifOutput extends StreamOutput implements OutputInterface
{
    public function format(LinterOutput $results): void
    {
        $failures = $results->getFailures();

        $driver = new ToolComponent('PHPLint');
        $driver->setInformationUri('https://github.com/overtrue/phplint');
        $driver->setVersion('9.1.0');

        $tool = new Tool($driver);

        $results = [];

        foreach ($failures as $file => $failure) {
            $result = new Result(new Message($failure['error']));

            $artifactLocation = new ArtifactLocation();
            $artifactLocation->setUri($this->pathToArtifactLocation($file));
            $artifactLocation->setUriBaseId('WORKINGDIR');

            $location = new Location();
            $physicalLocation = new PhysicalLocation($artifactLocation);
            $physicalLocation->setRegion(new Region($failure['line']));
            $location->setPhysicalLocation($physicalLocation);
            $result->addLocations([$location]);

            $results[] = $result;
        }

        $run = new Run($tool);
        $workingDir = new ArtifactLocation();
        $workingDir->setUri($this->pathToUri(getcwd() . '/'));
        $originalUriBaseIds = [
            'WORKINGDIR' => $workingDir,
        ];
        $run->addAdditionalProperties($originalUriBaseIds);
        $run->addResults($results);

        $log = new SarifLog([$run]);

        $jsonString = json_encode(
            $log,
            JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );

        $this->write($jsonString, true);
        fclose($this->getStream());
    }

    /**
     * Returns path to resource (file) scanned.
     */
    protected function pathToArtifactLocation(string $path): string
    {
        $workingDir = getcwd();
        if ($workingDir === false) {
            $workingDir = '.';
        }
        if (substr($path, 0, strlen($workingDir)) === $workingDir) {
            // relative path
            return substr($path, strlen($workingDir) + 1);
        }

        // absolute path with protocol
        return $this->pathToUri($path);
    }

    /**
     * Returns path to resource (file) scanned with protocol.
     */
    protected function pathToUri(string $path): string
    {
        if (parse_url($path, PHP_URL_SCHEME) !== null) {
            // already a URL
            return $path;
        }

        $path = str_replace(DIRECTORY_SEPARATOR, '/', $path);

        // file:///C:/... on Windows systems
        if (substr($path, 0, 1) !== '/') {
            $path = '/' . $path;
        }

        return 'file://' . $path;
    }
}