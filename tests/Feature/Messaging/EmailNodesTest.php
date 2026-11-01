<?php

use App\Enums\Agents\ActionEffect;
use App\Models\Connectors\Connector;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Email\EmailParseInboundNode;
use App\Nodes\Integrations\Email\EmailSendEmailNode;
use App\Nodes\Integrations\Email\EmailSendToManyNode;
use App\Nodes\Integrations\Email\EmailValidateAddressNode;
use App\Services\Connectors\SmtpMailer;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mime\Email;

/**
 * A run with a connected SMTP account in its workspace.
 *
 * @param  array<string, mixed>  $data
 */
function emailRun(array $data = []): Run
{
    $run = Run::factory()->create();

    ConnectorCredential::factory()->create([
        'workspace_id' => $run->workspace_id,
        'connector_id' => (Connector::query()->firstWhere('key', 'smtp') ?? Connector::factory()->create(['key' => 'smtp']))->id,
        'data' => ['host' => 'smtp.example.test', 'port' => 587, 'username' => 'u', 'api_key' => 'p', 'from_address' => 'hello@acme.test', 'from_name' => 'Acme', ...$data],
        'is_default' => true,
    ]);

    return $run;
}

describe('Email (SMTP) nodes', function () {
    it('registers the nodes under the smtp connector, sending as an external effect', function () {
        $registry = app(NodeRegistry::class);

        foreach (['email_send', 'email_send_to_many', 'email_validate_address', 'email_parse_inbound'] as $type) {
            expect($registry->resolve($type)->category())->toBe('smtp');
        }

        expect($registry->resolve('email_send')->effect([]))->toBe(ActionEffect::External)
            ->and($registry->resolve('email_validate_address')->effect([]))->toBe(ActionEffect::Read);
    });

    it('sends one message with the connection\'s From, a plain-text twin for HTML, and the extras', function () {
        $sent = [];
        $this->partialMock(SmtpMailer::class, fn ($mailer) => $mailer->shouldReceive('send')->once()->andReturnUsing(function (array $account, Email $message) use (&$sent): void {
            $sent = ['account' => $account, 'message' => $message];
        }));

        $output = (new EmailSendEmailNode)->execute(emailRun(), [
            'to' => 'ada@example.com, bob@example.com',
            'cc' => 'cc@example.com',
            'reply_to' => 'support@acme.test',
            'subject' => "Hello\r\nBcc: evil@example.com",
            'body' => '<p>Hi</p><p>There</p>',
            'is_html' => true,
        ], []);

        /** @var Email $message */
        $message = $sent['message'];

        expect($output['sent'])->toBeTrue()
            ->and($output['to'])->toBe(['ada@example.com', 'bob@example.com'])
            ->and($output['from'])->toBe('hello@acme.test')
            ->and($message->getFrom()[0]->getName())->toBe('Acme')
            ->and($message->getSubject())->toBe('Hello Bcc: evil@example.com')
            ->and($message->getBcc())->toBe([])
            ->and($message->getHtmlBody())->toBe('<p>Hi</p><p>There</p>')
            ->and($message->getTextBody())->toContain('Hi')->toContain('There')
            ->and($message->getReplyTo()[0]->getAddress())->toBe('support@acme.test')
            ->and($output['message_id'])->not->toBe('')
            ->and($sent['account']['host'])->toBe('smtp.example.test');
    });

    it('refuses a recipient that is not an address, an empty subject and a missing body', function () {
        $node = new EmailSendEmailNode;

        expect(fn () => $node->execute(emailRun(), ['to' => 'not-an-email', 'subject' => 'x', 'body' => 'y'], []))->toThrow(InvalidArgumentException::class, 'is not an email address')
            ->and(fn () => $node->execute(emailRun(), ['to' => 'a@b.co', 'subject' => ' ', 'body' => 'y'], []))->toThrow(InvalidArgumentException::class, 'subject is required')
            ->and(fn () => $node->execute(emailRun(), ['to' => 'a@b.co', 'subject' => 'x', 'body' => ''], []))->toThrow(InvalidArgumentException::class, 'body is required')
            ->and(fn () => $node->execute(emailRun(), ['to' => '', 'subject' => 'x', 'body' => 'y'], []))->toThrow(InvalidArgumentException::class, 'At least one recipient');
    });

    it('attaches the files it is given links to, and refuses one it can\'t download', function () {
        Http::fake([
            'files.test/invoice.pdf' => Http::response('%PDF-1.4', 200, ['Content-Type' => 'application/pdf']),
            'files.test/gone.pdf' => Http::response('no', 404),
        ]);
        $attached = null;
        $this->partialMock(SmtpMailer::class, fn ($mailer) => $mailer->shouldReceive('send')->once()->andReturnUsing(function (array $account, Email $message) use (&$attached): void {
            $attached = $message->getAttachments();
        }));

        $output = (new EmailSendEmailNode)->execute(emailRun(), ['to' => 'a@b.co', 'subject' => 'Invoice', 'body' => 'Attached', 'attachments' => ['https://files.test/invoice.pdf']], []);

        expect($output['attachment_count'])->toBe(1)
            ->and($attached[0]->getFilename())->toBe('invoice.pdf')
            ->and($attached[0]->getMediaType())->toBe('application')
            ->and(fn () => (new EmailSendEmailNode)->execute(emailRun(), ['to' => 'a@b.co', 'subject' => 'x', 'body' => 'y', 'attachments' => ['https://files.test/gone.pdf']], []))->toThrow(InvalidArgumentException::class, 'HTTP 404')
            ->and(fn () => (new EmailSendEmailNode)->execute(emailRun(), ['to' => 'a@b.co', 'subject' => 'x', 'body' => 'y', 'attachments' => ['ftp://files.test/a']], []))->toThrow(InvalidArgumentException::class, 'must be a link');
    });

    it('turns a refusal by the mail server into a sentence for the run log', function () {
        $this->partialMock(SmtpMailer::class, fn ($mailer) => $mailer->shouldReceive('send')->andThrow(new Exception('535 Authentication failed')));

        expect(fn () => (new EmailSendEmailNode)->execute(emailRun(), ['to' => 'a@b.co', 'subject' => 'x', 'body' => 'y'], []))
            ->toThrow(RuntimeException::class, 'did not send the message: 535 Authentication failed');
    });

    it('needs a From address on the connection', function () {
        expect(fn () => (new EmailSendEmailNode)->execute(emailRun(['from_address' => '']), ['to' => 'a@b.co', 'subject' => 'x', 'body' => 'y'], []))
            ->toThrow(InvalidArgumentException::class, 'valid From address');
    });

    it('sends to many people separately and reports who it could not reach', function () {
        $recipients = [];
        $this->partialMock(SmtpMailer::class, fn ($mailer) => $mailer->shouldReceive('send')->times(3)->andReturnUsing(function (array $account, Email $message) use (&$recipients): void {
            $recipients[] = array_map(fn ($address) => $address->getAddress(), $message->getTo());

            if ($message->getTo()[0]->getAddress() === 'bad@example.com') {
                throw new Exception('550 mailbox unavailable');
            }
        }));

        $output = (new EmailSendToManyNode)->execute(emailRun(), [
            'recipients' => ['a@example.com', 'bad@example.com', 'c@example.com'],
            'cc' => 'boss@example.com',
            'subject' => 'News',
            'body' => 'Hi',
        ], []);

        expect($recipients)->toBe([['a@example.com'], ['bad@example.com'], ['c@example.com']])
            ->and($output['sent'])->toBe(['a@example.com', 'c@example.com'])
            ->and($output['sent_count'])->toBe(2)
            ->and($output['failed_count'])->toBe(1)
            ->and($output['failed'][0]['email'])->toBe('bad@example.com')
            ->and($output['failed'][0]['error'])->toContain('550 mailbox unavailable');
    });

    it('can stop a send to many at the first failure, and caps the list', function () {
        $this->partialMock(SmtpMailer::class, fn ($mailer) => $mailer->shouldReceive('send')->once()->andThrow(new Exception('550 no')));

        expect(fn () => (new EmailSendToManyNode)->execute(emailRun(), ['recipients' => 'a@b.co,c@d.co', 'subject' => 'x', 'body' => 'y', 'stop_on_error' => true], []))->toThrow(RuntimeException::class, '550 no')
            ->and(fn () => (new EmailSendToManyNode)->execute(emailRun(), ['recipients' => array_map(fn (int $n): string => "u{$n}@example.com", range(1, 101)), 'subject' => 'x', 'body' => 'y'], []))->toThrow(InvalidArgumentException::class, 'At most 100');
    });

    it('validates an address\'s shape and flags free and throwaway providers without needing an account', function () {
        $node = new EmailValidateAddressNode;

        $good = $node->execute(Run::factory()->create(), ['email' => ' Ada@Gmail.com ', 'check_domain' => false], []);
        $bad = $node->execute(Run::factory()->create(), ['email' => 'nope', 'check_domain' => false], []);
        $throwaway = $node->execute(Run::factory()->create(), ['email' => 'x@mailinator.com', 'check_domain' => false], []);

        expect($good)->toMatchArray(['email' => 'ada@gmail.com', 'valid' => true, 'domain' => 'gmail.com', 'local_part' => 'ada', 'is_free_provider' => true, 'is_disposable' => false, 'domain_accepts_mail' => null])
            ->and($bad)->toMatchArray(['valid' => false, 'well_formed' => false, 'domain' => null])
            ->and($throwaway['is_disposable'])->toBeTrue();
    });

    it('reads what Postmark, Mailgun and SendGrid post into one shape', function () {
        $node = new EmailParseInboundNode;
        $run = Run::factory()->create();

        $postmark = $node->execute($run, ['payload' => [
            'FromFull' => ['Email' => 'Ada@Example.com', 'Name' => 'Ada'], 'ToFull' => [['Email' => 'help@acme.test']], 'Subject' => 'Hi', 'TextBody' => 'Full thread', 'StrippedTextReply' => 'Just this', 'MessageID' => 'pm-1',
            'Attachments' => [['Name' => 'a.pdf', 'ContentType' => 'application/pdf', 'ContentLength' => 10]],
        ]], []);
        $mailgun = $node->execute($run, ['payload' => ['sender' => 'bob@example.com', 'recipient' => 'help@acme.test', 'subject' => 'Yo', 'body-plain' => 'Body', 'stripped-text' => 'Short', 'Message-Id' => '<mg-1>', 'attachment-count' => '2', 'timestamp' => '1700000000']], []);
        $sendgrid = $node->execute($run, ['payload' => ['from' => 'Cy <cy@example.com>', 'to' => 'help@acme.test, "Doe, J" <j@example.com>', 'subject' => 'Hey', 'text' => 'Hello', 'envelope' => '{"from":"cy@example.com"}', 'headers' => "Message-ID: <sg-1>\nDate: Mon, 1 Jan 2026"]], []);

        expect($postmark)->toMatchArray(['provider' => 'postmark', 'from_email' => 'ada@example.com', 'from_name' => 'Ada', 'to' => ['help@acme.test'], 'text' => 'Full thread', 'reply_text' => 'Just this', 'message_id' => 'pm-1', 'attachment_count' => 1])
            ->and($mailgun)->toMatchArray(['provider' => 'mailgun', 'from_email' => 'bob@example.com', 'reply_text' => 'Short', 'message_id' => 'mg-1', 'attachment_count' => 2])
            ->and($sendgrid)->toMatchArray(['provider' => 'sendgrid', 'from_email' => 'cy@example.com', 'from_name' => 'Cy', 'to' => ['help@acme.test', 'j@example.com'], 'message_id' => 'sg-1', 'reply_text' => 'Hello']);
    });

    it('reads a payload with the usual names, unwraps a body, and refuses an empty one', function () {
        $node = new EmailParseInboundNode;
        $run = Run::factory()->create();

        $generic = $node->execute($run, ['payload' => ['body' => ['from' => 'z@example.com', 'subject' => 'S', 'text' => 'T']]], []);

        expect($generic)->toMatchArray(['provider' => 'generic', 'from_email' => 'z@example.com', 'subject' => 'S', 'text' => 'T'])
            ->and(fn () => $node->execute($run, ['payload' => []], []))->toThrow(InvalidArgumentException::class, 'request body is empty');
    });
});
