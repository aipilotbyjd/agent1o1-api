> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Analyze Video

## Node Inputs

### Required Fields

* **Video File**: Upload video (FLV, QuickTime, MPEG, MPEGPS, MPG, MP4, WEBM, WMV, or 3GPP)
* **Prompt**: Question or instruction for analysis. Be specific for best results
* **Video Model**: Choose AI model for analysis (Gemini 3.1 Pro, Gemini 3.7 Flash, Gemini 3.6 Flash)

### Optional Fields

* **Use Link**: Enable to use direct video URLs
  * Only supports publicly accessible media links (e.g., [https://example.com/video.mp4](https://example.com/video.mp4))
  * Does not support Google Drive, Dropbox, or other file-sharing links
  * URL must point directly to the media file
* **Temperature**: Controls analysis creativity (0-1, default: 1)
* **Cache Response**: Save responses for reuse

### Show As Input

The node allows you to configure certain parameters as dynamic inputs. You can enable these in the "Configure Inputs" section:

* **Use Link**: Boolean
  * true/false to use video URL instead of file upload
  * When enabled, allows direct input of video URLs

* **Prompt**: String
  * The specific question or instruction for analyzing the video
  * Example: "Describe the main events in this video"

* **video\_model\_preference**: String
  * Name of the AI model to use for video analysis
  * Accepted values: "Gemini 3.1 Pro", "Gemini 3.7 Flash", "Gemini 3.6 Flash"

* **Cache Response**: Boolean
  * true/false to enable/disable response caching
  * Helps reduce API calls for identical inputs

* **Temperature**: Number
  * Value between 0 and 1
  * Controls analysis consistency and creativity

When enabled as inputs, these parameters can be dynamically set by previous nodes in your workflow. If not enabled, the values set in the node configuration will be used.

## Node Output

* **Analysis**: AI's detailed response about the video content

## Node Functionality

The Analyze Video node can:

* Process video content with AI vision
* Extract text from video frames
* Generate video descriptions
* Answer queries about video content
* Identify objects and actions
* Analyze scenes and transitions
* Track movement and changes

## Available AI Models

Video analysis requires Gemini models with video support:

| Model                | Best For                           |
| -------------------- | ---------------------------------- |
| **Gemini 3.1 Pro**   | Detailed analysis, complex content |
| **Gemini 3.7 Flash** | Fast processing, balanced quality  |
| **Gemini 3.6 Flash** | Quick, basic analysis              |

## Common Use Cases

1. **Content Description**:

```text theme={"dark"}
Prompt: "Describe the main events in this video"
Use: Content cataloging, accessibility
```

2. **Text Extraction**:

```text theme={"dark"}
Prompt: "Extract any text that appears in the video"
Use: Subtitle extraction, text analysis
```

3. **Action Recognition**:

```text theme={"dark"}
Prompt: "List all activities and actions occurring in this video"
Use: Behavior analysis, event detection
```

4. **Object Tracking**:

```text theme={"dark"}
Prompt: "Track and describe the movement of specific objects"
Use: Motion analysis, surveillance
```

## Best Practices

1. **Video Length**:
   * Keep videos reasonably short for better analysis
   * Consider splitting longer videos into segments

2. **Prompt Engineering**:
   * Be specific in your instructions
   * Focus on one analysis task at a time
   * Include temporal aspects if timing is important

3. **Model Selection**:
   * Use Gemini 3.1 Pro for detailed analysis
   * Choose Gemini 3.7 Flash or 3.6 Flash for quick, basic analysis

4. **Temperature Usage**:
   * Low (0-0.3): Consistent, factual analysis
   * Medium (0.4-0.7): Balanced analysis
   * High (0.8-1.0): Creative descriptions

## Important Considerations

1. Cost: 20 credits per video analysis
2. Processing time increases with video length
3. Analysis quality depends on video resolution and clarity
4. When using the `Use Link` option make sure the link has the appropriate file extension

In summary, the Analyze Video node provides powerful video analysis capabilities using state-of-the-art AI vision models, suitable for a wide range of applications from content description to detailed motion analysis.
