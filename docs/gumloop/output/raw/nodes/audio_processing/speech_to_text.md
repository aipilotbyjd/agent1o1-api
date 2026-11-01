> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Speech to Text

The Speech to Text node converts audio files into written text using AI transcription technology. This documentation covers everything you need to know about using this node effectively in your Gumloop workflows.

## Node Inputs

### Required Fields

* **Audio File**: The audio recording to be transcribed
  * **Supported Formats**: mp3, mp4, mpeg, mpga, m4a, wav, webm, mov
  * **Maximum File Size**: 25MB

### Optional Fields

* **Use Link**: When enabled, allows you to provide a URL to the audio file instead of uploading
  * **Link**: The URL of the audio file to transcribe (required if "Use Link" is enabled)
* **Model**: The AI system used for transcription
  * Current available model: OpenAI Whisper

## Node Output

* **Transcript**: The transcribed text content from the audio file

## Node Functionality

The Speech to Text node leverages OpenAI's Whisper model to:

* Convert spoken words in audio files into accurate text
* Maintain proper punctuation and capitalization
* Recognize and transcribe multiple languages
* Process various audio formats and quality levels
* Support batch processing via Loop Mode

<div align="center">
  ```mermaid theme={"dark"}
  %%{init: {'theme':'neutral', 'themeVariables': { 'primaryColor': '#f5f5f5', 'primaryBorderColor': '#ddd'}}}%%
  flowchart LR
      A[Audio Input] --> B[Speech to Text Node]
      B --> C[Text Transcript]
      
      style B fill:#F06292,color:white
  ```
</div>

## OpenAI Whisper Limitations

The current implementation uses OpenAI's Whisper model, which has the following limitations:

1. **File Size Restriction**:
   * Maximum file size: 25MB

2. **Accuracy Factors**:
   * Audio quality affects transcription accuracy
   * Background noise can reduce precision
   * Heavy accents may result in lower accuracy
   * Technical or specialized terminology may not be recognized correctly

## When to Use

The Speech to Text node is ideal when you need to:

### Convert Recordings

* Meeting recordings for documentation
* Interview audio for research
* Voice notes for personal productivity
* Lecture content for educational materials

### Create Documentation

* Generate meeting minutes automatically
* Create searchable interview transcripts
* Produce podcast transcripts for accessibility
* Develop text-based course materials

### Process Audio Content

* Extract information from audio messages
* Make audio content searchable
* Prepare data for sentiment analysis
* Archive spoken information in text format

## Common Use Cases

| Use Case                  | Input                              | Output                        | Business Value                                                     |
| ------------------------- | ---------------------------------- | ----------------------------- | ------------------------------------------------------------------ |
| **Meeting Documentation** | Weekly meeting recording (MP3/MP4) | Complete text transcript      | Searchable records, easier follow-up, team alignment               |
| **Content Repurposing**   | Podcast or video content           | Text for articles, blog posts | Content multiplier, improved SEO, wider audience reach             |
| **Customer Research**     | Interview recordings               | Text for analysis             | Easier pattern recognition, quote extraction, theme identification |
| **Legal Documentation**   | Recorded statements                | Written documentation         | Compliance records, searchable archives, supporting evidence       |

## Example Workflow: Podcast Content Repurposing

<div align="center">
  ```mermaid theme={"dark"}
  %%{init: {'theme':'neutral', 'themeVariables': { 'primaryColor': '#f5f5f5', 'primaryBorderColor': '#ddd'}}}%%
  flowchart LR
      A[Drive File Reader\nPodcast MP3] --> B[Speech to Text]
      B --> C[Ask AI\n'Create podcast summary']
      B --> D[Extract Data\n'Extract key topics']
      C --> E[Google Docs Writer\n'Podcast Summary']
      D --> F[Airtable Writer\n'Content Calendar']
  ```
</div>

This workflow automatically:

1. Reads podcast audio files
2. Transcribes them to text
3. Creates a summary and extracts key topics
4. Outputs to Google Docs and Airtable for content planning

## Credits Usage

* The Speech to Text node consumes 20 credits per run
* Loop Mode processing uses credits for each file processed

In summary, the Speech to Text node provides powerful audio transcription capabilities, making your audio content accessible, searchable, and actionable within your Gumloop workflows.
