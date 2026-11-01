<?php

namespace App\Services\Triggers\Push;

use App\Contracts\Triggers\ProviderIngress;
use App\Contracts\Triggers\PushTriggerSource;
use App\Models\Triggers\Trigger;

/**
 * The providers that can push events to triggers: one source and one ingress
 * each. A new provider is added here and nowhere else in the pipeline.
 */
class PushSources
{
    /**
     * @var array<string, PushTriggerSource>
     */
    private array $sources = [];

    /**
     * @var array<string, ProviderIngress>
     */
    private array $ingresses = [];

    public function __construct(
        SlackMessageSource $slackSource,
        SlackIngress $slackIngress,
        GmailMessageSource $gmailSource,
        OutlookMessageSource $outlookSource,
        GoogleDriveSource $driveSource,
        GoogleCalendarSource $calendarSource,
        GitHubSource $githubSource,
        GoogleSheetsSource $sheetsSource,
        GoogleDocsSource $docsSource,
        GoogleFormsSource $formsSource,
        YouTubeUploadsSource $youtubeSource,
        NotionSource $notionSource,
        LinearSource $linearSource,
        JiraSource $jiraSource,
        TelegramSource $telegramSource,
        DiscordSource $discordSource,
        TwilioSource $twilioSource,
        TypeformSource $typeformSource,
        MailchimpSource $mailchimpSource,
        KitSource $kitSource,
        ActiveCampaignSource $activeCampaignSource,
        ResendSource $resendSource,
        PostmarkSource $postmarkSource,
        MailgunSource $mailgunSource,
        TrelloSource $trelloSource,
        AsanaSource $asanaSource,
        ClickUpSource $clickUpSource,
        AirtableSource $airtableSource,
        MondaySource $mondaySource,
        PipedriveSource $pipedriveSource,
        ZohoSource $zohoSource,
        ZohoDeskSource $zohoDeskSource,
        StripeSource $stripeSource,
        ShopifySource $shopifySource,
    ) {
        foreach ([$slackSource, $gmailSource, $outlookSource, $driveSource, $calendarSource, $githubSource, $sheetsSource, $docsSource, $formsSource, $youtubeSource, $notionSource, $linearSource, $jiraSource, $telegramSource, $discordSource, $twilioSource, $typeformSource, $mailchimpSource, $kitSource, $activeCampaignSource, $resendSource, $postmarkSource, $mailgunSource, $trelloSource, $asanaSource, $clickUpSource, $airtableSource, $mondaySource, $pipedriveSource, $zohoSource, $zohoDeskSource, $stripeSource, $shopifySource] as $source) {
            $this->sources[$source->provider()] = $source;
        }

        foreach ([
            $slackIngress,
            new GmailIngress,
            new OutlookIngress,
            new GoogleChannelIngress('google_drive'),
            new GoogleChannelIngress('google_calendar'),
            new GitHubIngress,
            new GoogleChannelIngress('google_sheets'),
            new GoogleChannelIngress('google_docs'),
            new GoogleFormsIngress,
            new YouTubeUploadsIngress,
            new NotionIngress,
            new LinearIngress,
            new JiraIngress,
            new TelegramIngress,
            new DiscordIngress,
            new TwilioIngress,
            new TypeformIngress,
            new MailchimpIngress,
            new KitIngress,
            new ActiveCampaignIngress,
            new ResendIngress,
            new PostmarkIngress,
            new MailgunIngress,
            new TrelloIngress,
            new AsanaIngress,
            new ClickUpIngress,
            new AirtableIngress,
            new MondayIngress,
            new PipedriveIngress,
            new ZohoIngress,
            new ZohoDeskIngress,
            new StripeIngress,
            new ShopifyIngress,
        ] as $ingress) {
            $this->ingresses[$ingress->provider()] = $ingress;
        }
    }

    /**
     * The source that can push events to the trigger, if its provider is on.
     */
    public function forTrigger(Trigger $trigger): ?PushTriggerSource
    {
        foreach ($this->sources as $source) {
            if ($source->isEnabled() && $source->supports($trigger)) {
                return $source;
            }
        }

        return null;
    }

    /**
     * The source for a provider key.
     */
    public function source(string $provider): ?PushTriggerSource
    {
        return $this->sources[$provider] ?? null;
    }

    /**
     * The ingress for a provider key, only while that provider's push is on.
     */
    public function ingress(string $provider): ?ProviderIngress
    {
        $source = $this->sources[$provider] ?? null;

        return $source !== null && $source->isEnabled() ? ($this->ingresses[$provider] ?? null) : null;
    }

    /**
     * Whether any provider's push is on.
     */
    public function anyEnabled(): bool
    {
        foreach ($this->sources as $source) {
            if ($source->isEnabled()) {
                return true;
            }
        }

        return false;
    }
}
