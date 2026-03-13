# AKI.IO API Interfaces

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
![GitHub repo size](https://img.shields.io/github/repo-size/aki-io-labs/aki-io)
![GitHub stars](https://img.shields.io/github/stars/aki-io-labs/aki-io?style=social)

> **High-performance AI model services via simple API interfaces.**  
> Official client libraries for connecting to the [AKI.IO](https://aki.io) platform.

## 🚀 Overview

This repository provides official client interfaces (libraries) for seamlessly integrating with the [AKI.IO](https://aki.io) platform. AKI.IO offers a user-friendly API to access leading open-source or open-weight AI models, enabling developers to build intelligent applications without the complexity and cost of managing infrastructure.

The platform is designed for business and professional use, offering enterprise-grade security, GDPR-compliance, scalable deployment, and transparent, token-based billing.

## ✨ Key Features

- **Simple Integration**: Pre-built client libraries for popular languages.
- **Access to Top AI Models**: Interface with state-of-the-art LLMs and image generation models.
- **OpenAI Compatibility**: Easily switch from other providers with familiar endpoints.
- **Enterprise-Grade**: Running on AIME HPC GPU cloud instances in ISO 27001 & SOC 2 Type II certified adat centers, GDPR-compliant, hosted in Europe.
- **Secure by Design**: API keys are scoped and managed per use case for enhanced control.
  
## 📦 Available Client Libraries

This repository contains interfaces for the following programming environments:

| Language | Directory | Status |
| :--- | :--- | :--- |
| ![Python](https://img.shields.io/badge/Python-3776AB?style=flat&logo=python&logoColor=white) | `/python` | ✅ Available |
| ![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=flat&logo=javascript&logoColor=black) | `/js` | ✅ Available |
| Others | Coming Soon | 🚧 Planned |

## 🏁 Getting Started

Follow these steps to start using the AKI.IO API:

### 1. Get Your API Key
1. Sign up for a free account at [aki.io/signup](https://aki.io/signup) to receive **10 EUR free trial credits**.
2. After logging in, navigate to your **User Profile** page at [aki.io/admin/user](https://aki.io/admin/user) to view your API Key.
3. **Important**: Keep your API key secure. It's bound to your user and should not be embedded in public code.

### 2. Make Your First Request

Here’s a quick example to test your setup:


**Python Example**

Checkout this repo and install the **aki-io** pip with following command:

```bash
pip install "git+https://github.com/aki-io-labs/aki-io.git#subdirectory=python"
```

Change into the 'python/examples' directory and run:

```bash
python3 llm_simple_example.py
```

**JavaScript Example**

Checkout this repo and change into the 'js/examples' directory and run:


```bash
node llm_simple_example.js
```
