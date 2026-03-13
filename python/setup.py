from setuptools import setup

with open("pypi_README.md", "r", encoding="utf-8") as file:
    long_description = file.read()


setup(
    name='aki-io',
    version='1.0.0',
    author='AKI',
    author_email='hello@aki.io',
    packages=['aki_io'],
    description="AKI-IO python client interface",
    long_description=long_description,
    keywords=["python", "aki", "async", "api-server", "ai", "dl", "ml", "llm", "aki.io", "transformer", "diffuser"],
    install_requires=[
        'requests >= 2.31.0',
        'aiohttp >= 3.9.0',
    ],
    zip_safe=False
)
