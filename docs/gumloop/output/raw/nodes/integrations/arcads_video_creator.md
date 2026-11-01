> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Arcads Video Creator

This document explains the Arcads Video Creator node, which generates professional AI videos using the Arcads platform.

## Node Inputs

### Required Fields

* **Title**: The title for your video
  * Example: "Product Launch Announcement"
* **Script**: The content for your video narration
  * Must contain a minimum of 100 characters
  * Example: "Hey, have you heard about the new Arcads node on Gumloop? It's awesome! Create stunning automated video ads. I bet you have never seen this before"

### Optional Fields

* **Product**: Choose the product to feature in your video
  * Select from the options configured on your Arcads platform
* **Situation**: Select the scenario or setting for your video
  * Choose from various environments and contexts
  * Example: "Amina - outside beach street AI Avatar (Calm)"
  * **You can select multiple situations** to create separate videos for each one
* **Folder**: Specify where to save the video in your Arcads account
* **Actors**: Select the AI actors to appear in your video
  * Available actors depend on your Arcads subscription plan
  * **You can select multiple actors** to create separate videos for each one

### Multiple Selections

When you select multiple actors and/or situations, the node will create separate videos for each combination:

* If you select 2 actors and 3 situations, the node will generate 6 different videos (2×3)
* Each combination will have the same script but with different visual presentations
* This is ideal for A/B testing different video styles with the same content

### Show As Input

The node allows you to configure certain parameters as dynamic inputs. You can enable these in the "Configure Inputs" section:

* **Title**: String
  * The title for your video

* **Script**: String
  * The script/content for your video narration

* **Product**: String
  * Product selection to feature in the video

* **Folder**: String
  * Destination folder in your Arcads account

* **Actors**: String
  * AI actors to appear in your video

When enabled as inputs, these parameters can be dynamically set by previous nodes in your workflow. If not enabled, the values set in the node configuration will be used.

## Node Output

* **Video ID**: Identifier for the generated video

## Node Functionality

The Arcads Video Creator node:

* Creates AI-generated videos for marketing, announcements, and presentations
* Supports various scenarios and settings
* Integrates with the Arcads platform
* Processes video creation requests asynchronously
* Supports batch processing via Loop Mode

## Common Use Cases

1. **Marketing Campaign Videos**:

```text theme={"dark"}
Input: Product descriptions from Google Sheets
Output: Customized product videos for each item
Use: Digital marketing, social media advertising
```

2. **Personalized Customer Messages**:

```text theme={"dark"}
Input: Customer data from CRM
Output: Personalized video greetings
Use: Customer engagement, special offers
```

3. **Training Content**:

```text theme={"dark"}
Input: Training scripts from documents
Output: Professional instructional videos
Use: Internal training, onboarding
```

4. **A/B Testing Video Content**:

```text theme={"dark"}
Input: Single marketing script
Setup: Select multiple actors and situations
Output: Multiple video variations to test
Use: Determine which video performs best
```

## Loop Mode Pattern

When enabled, Loop Mode allows you to create multiple videos in a single workflow:

```text theme={"dark"}
Input: List of scripts & titles
Process: Generate a video for each item
Output: Multiple video IDs
```

## Example Workflows

### 1. Content Repurposing

```text theme={"dark"}
Ask AI (Generate video scripts) → Arcads Video Creator → Slack Message Sender
Setup:
- Ask AI: Convert blog posts into video scripts
- Arcads Video Creator: Create videos from scripts
- Slack Message Sender: Share video links with team
Purpose: Repurpose blog content as engaging videos
```

### 2. Batch Video Creation

```text theme={"dark"}
Google Sheets Reader → Arcads Video Creator (Loop Mode) → Error Shield → Airtable Writer
Setup:
- Google Sheets Reader: Read video titles and scripts
- Arcads Video Creator: Create videos for each row (in Loop Mode)
- Error Shield: Handle any creation errors
- Airtable Writer: Log video IDs and URLs
Purpose: Bulk create videos from spreadsheet data
```

## API Credentials Setup

To use the Arcads Video Creator node, you'll need to connect your Arcads account:

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1062045154" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="Vimeo Video" />
</div>

1. Go to your [Arcads Dashboard](https://app.arcads.ai/dashboard)
2. Navigate to the API Settings section
3. Copy your API Key
4. Add it to Gumloop in the [Connectors page](https://www.gumloop.com/personal/connectors)

## Important Considerations

1. **Video Generation Time**:
   * Videos are not generated instantly
   * After the node runs, you'll receive a confirmation message:
     ```text theme={"dark"}
     The videos are generating:
     1. Go to https://app.arcads.ai/dashboard
     2. Access the folder: [Your Folder Name]
     3. Click on "[Your Video Title]" to view the results.
     ```
   * Check your Arcads dashboard for the finished video

2. **Script Guidelines**:
   * Minimum 100 characters required
   * Credits are used for each video generation, so avoid typos and mistakes
   * Ensure scripts are appropriate for your intended audience

3. **Content Policies**:
   * No violent, racist, or adult content
   * Refrain from generating harmful content
   * Follow [Arcads content guidelines](https://www.arcads.ai)

4. **Subscription Limitations**:
   * Available actors and situations depend on your Arcads subscription plan
   * Check your Arcads account for available options

5. **Multiple Selections Impact**:
   * Each actor-situation combination creates a separate video
   * Be mindful when selecting multiple options as this multiplies the number of videos generated
   * Consider your Arcads credit usage when creating multiple variants

## Learn More

* [Arcads Website](https://www.arcads.ai)
* [Arcads Documentation](https://www.arcads.ai/blog)
