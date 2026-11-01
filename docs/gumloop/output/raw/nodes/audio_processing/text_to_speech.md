> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Text to Speech

This document explains the Text to Speech node, which converts written text into spoken audio files.

## Node Inputs

### Required Fields

* **Text**: Content to convert to speech
* **New File Name**: Name for output MP3

### Optional Fields

* **Model**: Voice generation system (ElevenLabs)
  * Can choose from different voices

## Node Output

* **MP3 File**: Generated audio file

## Node Functionality

The Text to Speech node:

* Creates natural speech
* Generates MP3 files
* Supports multiple voices
* Supports batch processing via loop mode

## When to Use

Use this node when you need to:

1. **Create Audio Content**:
   * Generate podcasts
   * Make announcements
   * Create voice-overs

2. **Accessibility Features**:
   * Audio versions of text
   * Reading assistance
   * Voice notifications

3. **Content Production**:
   * Audio tutorials
   * Voice messaging
   * Educational content

## Common Use Cases

1. **Article Narration**:

```text theme={"dark"}
Text: Blog post content
Output: Audio version for listeners
Use: Content accessibility
```

2. **Automated Messages**:

```text theme={"dark"}
Text: "Welcome to our service!"
Output: Welcome message.mp3
Use: Phone systems, websites
```

3. **Language Learning**:

```text theme={"dark"}
Text: Foreign language phrases
Output: Pronunciation guides
Use: Educational materials
```

## Important Considerations

1. Requires Authentication with ElevenLabs - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. You can check the voice samples [here](https://elevenlabs.io/text-to-speech)

In summary, the Text to Speech node helps create audio content from text, perfect for accessibility and content production in your Gumloop workflows.
