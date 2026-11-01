<?php

namespace App\Nodes\AiAutomation;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use App\Nodes\Support\FilePayload;
use Laravel\Ai\Image;

/**
 * Makes an image from a description. The file comes out in the shape other
 * steps can attach to an email or post.
 */
class AiImageNode extends AbstractAiNode
{
    private const int MAX_PROMPT_CHARACTERS = 4000;

    public function type(): string
    {
        return 'ai_image';
    }

    public function name(): string
    {
        return 'AI Generate Image';
    }

    public function icon(): string
    {
        return 'image-add-01';
    }

    public function description(): string
    {
        return 'Creates an image from a description, such as a product shot, an illustration or a social graphic, and returns it as a file.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['prompt'],
            'properties' => [
                'prompt' => Field::textarea('Describe the image', 'Subject, setting, style and mood. Use {{templates}} to take it from an earlier step.'),
                'shape' => Field::select('Shape', ['square' => 'Square', 'landscape' => 'Landscape', 'portrait' => 'Portrait'], null, 'square'),
                'quality' => Field::advanced(Field::select('Quality', ['standard' => 'Standard', 'high' => 'High'], 'High takes longer and costs the provider more.', 'standard')),
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'file' => ['title' => 'Image file', 'description' => 'The image as a file: filename, mime_type, size and content_base64. Attach it with {{ nodes.image.file }}.'],
                'size' => ['title' => 'Size', 'description' => 'The file size in bytes.'],
                'usage' => self::usageSchema(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $prompt = $this->requiredText($config, 'prompt', 'The description');

        if (mb_strlen($prompt) > self::MAX_PROMPT_CHARACTERS) {
            throw new \InvalidArgumentException('The description is too long ('.number_format(mb_strlen($prompt)).' characters; the limit is '.number_format(self::MAX_PROMPT_CHARACTERS).').');
        }

        $pending = Image::of($prompt)->timeout(180);

        $pending = match ($config['shape'] ?? 'square') {
            'landscape' => $pending->landscape(),
            'portrait' => $pending->portrait(),
            default => $pending->square(),
        };

        if (($config['quality'] ?? 'standard') === 'high') {
            $pending = $pending->quality('high');
        }

        $response = $pending->generate();
        $image = $response->firstImage();
        $mime = $image->mime();
        $file = FilePayload::make($image->content(), 'image.'.match ($mime) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        }, $mime);

        return ['file' => $file, 'size' => $file['size'], 'usage' => [...$response->usage->toArray(), ...$response->meta->toArray()]];
    }
}
