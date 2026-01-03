from setuptools import setup

setup(
    name='aki-io',
    version='1.0.0',
    author='AKI',
    author_email='hello@aki.io',
    packages=['aki_io'],
    install_requires=[
        'requests==2.31.0',
        'aiohttp==3.9.0',
    ],
    zip_safe=False
)
