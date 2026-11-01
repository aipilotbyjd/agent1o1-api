> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Create Twitter Thread

This document outlines the functionality and characteristics of the Create Twitter Thread node, which enables automated thread creation on Twitter/X.

## Node Inputs

### Required Field

* **Tweets**: List of tweet texts to form the thread
  * First item becomes main tweet
  * Subsequent items become replies

### Optional Field

* **Remove Hashtags**: Toggle to remove trailing hashtags from all tweets

## Node Output

* **Tweet URL**: Link to the first tweet in the thread

## When To Use

The Create Twitter Thread node is valuable for longer-form Twitter content. Common use cases include:

* **Detailed Updates**: Share multi-part announcements
* **Story Telling**: Break down long stories into tweets
* **Step-by-Step Content**: Share tutorials or instructions
* **Live Coverage**: Post event updates sequentially

**Specific example**:

```text theme={"dark"}
  "🚀 Announcing our new product launch!",
  "✨ Key Feature #1: AI-powered analytics",
  "📊 Key Feature #2: Real-time reporting",
  "🔗 Learn more: example.com/launch"
```

## Important Considerations:

1. Requires Authentication with Twitter/X - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. Each tweet must follow character limits
3. Input must be list format
4. Tweets post in sequence
5. Thread posts immediately

In summary, the Create Twitter Thread node streamlines the creation of connected tweet sequences for longer-form content sharing.
