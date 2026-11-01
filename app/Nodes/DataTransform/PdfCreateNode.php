<?php

namespace App\Nodes\DataTransform;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use App\Nodes\Support\FilePayload;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Makes a PDF from Markdown or HTML: an invoice, a report, a letter, a
 * summary to attach to an email. The renderer is locked down: it fetches no
 * remote files, reads no local ones and runs no script, so a document built
 * from outside text cannot reach anything.
 */
class PdfCreateNode extends PureDataNode
{
    private const int MAX_CHARACTERS = 1000000;

    public function type(): string
    {
        return 'pdf_create';
    }

    public function name(): string
    {
        return 'Create PDF';
    }

    public function icon(): string
    {
        return 'pdf-01';
    }

    public function description(): string
    {
        return 'Makes a PDF from Markdown or HTML, such as an invoice, a report or a letter, ready to attach to an email.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['content'],
            'properties' => [
                'content' => Field::textarea('Content', 'Markdown or HTML. Use {{templates}} to build it from earlier steps.'),
                'format' => Field::select('Written as', ['markdown' => 'Markdown', 'html' => 'HTML'], null, 'markdown'),
                'title' => Field::text('Title', 'Shown as the document title and used for the file name.', 'Monthly report'),
                'paper' => Field::advanced(Field::select('Paper', ['a4' => 'A4', 'letter' => 'US Letter'], null, 'a4')),
                'orientation' => Field::advanced(Field::select('Orientation', ['portrait' => 'Portrait', 'landscape' => 'Landscape'], null, 'portrait')),
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'file' => ['title' => 'PDF file', 'description' => 'The PDF as a file: filename, mime_type, size and content_base64. Attach it with {{ nodes.pdf.file }}.'],
                'size' => ['title' => 'Size', 'description' => 'The file size in bytes.'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $content = $this->text($config, 'content');

        if (trim($content) === '') {
            throw new InvalidArgumentException('There is nothing to put in the PDF.');
        }

        if (mb_strlen($content) > self::MAX_CHARACTERS) {
            throw new InvalidArgumentException('The content is too long for one PDF.');
        }

        $title = trim((string) ($config['title'] ?? '')) ?: 'Document';
        $body = ($config['format'] ?? 'markdown') === 'html'
            ? $content
            : Str::markdown($content, ['html_input' => 'strip', 'allow_unsafe_links' => false]);

        $html = '<!doctype html><html><head><meta charset="utf-8"><title>'.e($title).'</title><style>'
            .'body{font-family:DejaVu Sans,sans-serif;font-size:12px;line-height:1.5;color:#111}h1,h2,h3{line-height:1.25}h1{font-size:22px}h2{font-size:17px}h3{font-size:14px}'
            .'table{border-collapse:collapse;width:100%}th,td{border:1px solid #ccc;padding:5px 8px;text-align:left}th{background:#f3f4f6}code,pre{font-family:DejaVu Sans Mono,monospace;background:#f3f4f6}pre{padding:8px}blockquote{border-left:3px solid #ccc;margin-left:0;padding-left:12px;color:#444}'
            .'</style></head><body>'.$body.'</body></html>';

        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setChroot([sys_get_temp_dir().DIRECTORY_SEPARATOR.'pdf-no-files']);
        $options->setDefaultFont('DejaVu Sans');

        $pdf = new Dompdf($options);
        $pdf->loadHtml($html);
        $pdf->setPaper(($config['paper'] ?? 'a4') === 'letter' ? 'letter' : 'a4', ($config['orientation'] ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait');
        $pdf->render();

        $file = FilePayload::make($pdf->output(), (Str::slug($title) ?: 'document').'.pdf', 'application/pdf');

        return ['file' => $file, 'size' => $file['size']];
    }
}
