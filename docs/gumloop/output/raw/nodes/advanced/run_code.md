> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Run Code

## Node Inputs

* **Inputs**: Define the arguments that you want your function to have. Example input could be named 'location'.
* **Function Body**: This is the body of the function that you want to run. You can write a Python or JavaScript function here that will execute when the node runs. You can access the 'Inputs' through their names within your function. Outputs can be set in this function and will be accessible through Gumloop outputs.
* **Outputs**: Define the outputs that you want your function to have. An example output could be 'temperature'. This is the name of the outputs of your function which will be returned as values accessible through Gumloop outputs.

## Available Libraries

### Python Libraries

You can import and use a wide range of Python libraries in your function. Here some of the available packages, organized by category:

* **Data Analysis and Manipulation**: pandas, numpy, scipy, xarray
* **Image Processing**: opencv-python, imageio, scikit-image
* **Machine Learning**: scikit-learn, joblib
* **Natural Language Processing**: nltk, textblob, spacy, gensim
* **Web Scraping**: beautifulsoup4, requests, urllib3, aiohttp
* **Plotting and Visualization**: matplotlib, seaborn, plotly, bokeh
* **File Handling**: openpyxl, xlrd, python-docx
* **Audio Processing**: librosa, soundfile
* **Testing**: pytest
* **Timezone**: pytz
* **Web Server**: tornado

### Full List of Packages:

For a complete list of available packages, please refer to our [requirements.txt](https://storage.googleapis.com/agenthub-public/docs/requirements.txt).

To import a Python package, simply use the standard import statement within your function body. For example:

```python theme={"dark"}
def function():
    import pandas as pd
    import numpy as np

    # Your code

    return
```

### JavaScript Libraries

The following JavaScript libraries are available:

* **AI and Machine Learning SDKs**: ai, @ai-sdk/openai, @ai-sdk/azure, @ai-sdk/anthropic, @ai-sdk/amazon-bedrock, @ai-sdk/google, @ai-sdk/google-vertex, @ai-sdk/mistral, @ai-sdk/cohere, @ai-sdk/groq

To import a JavaScript package, use the `require` statement within your function body. For example:

```javascript theme={"dark"}
function func() {
  const { openai, createOpenAI } = require('@ai-sdk/openai');

  // Your code

  return {};
}
```

## Node Output

The output of this node will vary based on the user-defined function's returns. Outputs are defined by the user and can consist of any data type or structure the function is capable of returning.

# Node Functionality

## When To Use

This node is incredibly versatile and can be used whenever there's a need to run arbitrary Python or JavaScript code within a workflow. It allows for flexible inputs and outputs, meaning it can be adapted to perform a wide range of tasks that require custom code execution. This could be anything from data manipulation, calling external APIs, complex calculations, or conditionally handling information based on inputs provided to the node.

It's especially useful in scenarios where existing nodes do not meet your requirements, and you need a quick, customizable solution that doesn't require developing a new node from scratch. Use this node to embed Python or JavaScript scripts that extend the functionality of your workflows, making them more powerful and tailored to your specific needs.
