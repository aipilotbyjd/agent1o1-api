> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Define AI Function

This node allows you to specify an AI function with clear parameters and a purpose. It's designed to set up a framework for AI models to understand and respond with structured data.

## Node Inputs

The inputs for this node are divided into three main parts, each with specific requirements:

* **Name**: This is where you name your function. It's crucial as it serves as the identifier for the AI model. For example, you might name a function 'get\_weather' to retrieve weather information.

* **Description**: This section is for describing what your function does. The description helps the AI model understand the purpose of your function, making it a crucial part of the setup.

* **Parameters**: Parameters are the inputs that your function will accept. Each parameter requires:
  * A **Name**, such as 'location', which acts as an identifier.
  * A **Type**, indicating whether the parameter is a string, number, or boolean.
  * A **Description**, which explains what the parameter is used for. This helps in understanding how to use the function correctly.

## Node Output

* **Function JSON**: After setting up your AI function, the node outputs the configuration in a JSON format. This output contains the name, description, and parameters of your function, structured in a way that AI models can understand and work with.

## Node Functionality

The "Define AI Function" node is designed for creating structured AI functions that demand specific inputs and provide structured outputs. It forces AI models to adhere to a format that you define, ensuring that responses are predictable and in the desired structure.

## When To Use

Use this node when you need to interact with AI models in a controlled and structured manner. It's particularly useful in scenarios where you need specific information from an AI model, and you want to make sure that the model understands exactly what you're asking for.

* **Integrating with AI Models**: When integrating an automation workflow with an AI model, this node can help define the specific functions and the format in which you expect responses.

* **Structured Responses**: If your workflow depends on receiving data in a structured format from an AI model, using this node to define the function ensures that the model returns data as you expect.

* **Custom AI Functions**: When you have unique requirements that aren't met by standard AI functions, the "Define AI Function" node allows you to specify what you need in detail, making it easier for the AI model to serve those needs.
