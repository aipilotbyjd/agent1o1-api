<?php

namespace App\Services\Agents\Knowledge;

use App\Enums\Agents\KnowledgeSourceType;
use App\Services\Agents\Knowledge\Readers\GitHubReader;
use App\Services\Agents\Knowledge\Readers\GmailReader;
use App\Services\Agents\Knowledge\Readers\GoogleDriveReader;
use App\Services\Agents\Knowledge\Readers\KnowledgeReader;
use App\Services\Agents\Knowledge\Readers\OutlookReader;
use App\Services\Agents\Knowledge\Readers\SlackReader;
use App\Services\Agents\Knowledge\Readers\UrlReader;
use Illuminate\Contracts\Container\Container;

/**
 * The reader for each kind of knowledge source.
 */
class KnowledgeReaders
{
    /**
     * @var array<string, class-string<KnowledgeReader>>
     */
    private const array READERS = [
        'url' => UrlReader::class,
        'google_drive' => GoogleDriveReader::class,
        'gmail' => GmailReader::class,
        'outlook' => OutlookReader::class,
        'github' => GitHubReader::class,
        'slack' => SlackReader::class,
    ];

    public function __construct(private readonly Container $container) {}

    public function for(KnowledgeSourceType $type): KnowledgeReader
    {
        return $this->container->make(self::READERS[$type->value]);
    }
}
