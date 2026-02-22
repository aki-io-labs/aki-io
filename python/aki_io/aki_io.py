# AKI.IO Model Hub API Python interface
#
# Copyright (c) AKI.IO GmbH and affiliates. Find more info at https://aki.io
#
# This software may be used and distributed according to the terms of the MIT LICENSE

import aiohttp
import base64
import asyncio
import requests
import time
import json
import re


DEFAULT_PROGRESS_INTERVAL = 0.2


class Aki():
    """
    An interface for interacting with the AKI IO ai model hub services

    Constructor

    Args:
        endpoint_name (str): The name of the API endpoint
        api_key (str, optional): The api_key, register for your AKI api key at https://aki.io
        session (aiohttp.ClientSession): Give existing session to Aki to make upcoming requests in given session. 
            Defaults to None.
        output_binary_format (str, optional): Output format of binary data possible options 'base64', 'byte_string'
            Defaults to 'byte_string'.  
        api_server (str, optional): overwrite the base URL of the AKI.IO server
        raise_exceptions (bool, optional): Whether to exceptions are raised in case of network errors . Defaults to False.

    """

    def __init__(
            self,
            endpoint_name,
            api_key=None,
            session=None,
            output_binary_format='base64',
            api_server='https://aki.io',
            raise_exceptions=False
        ):
        

        self.api_server = api_server
        self.api_server_url = api_server + "/api/"
        self.endpoint_name = endpoint_name
        self.api_key = api_key
        self.session = session
        self.client_session_auth_key = None
        self.output_binary_format = output_binary_format
        self.raise_exceptions = raise_exceptions
        self.canceled_jobs = []
        self.progress_input_params = {} # key job_id



    async def __aexit__(self):
        await self.session.close()


    async def init_api_key_async(self, api_key=None, session=None):
        """Initialize and validate api key.

        Args:
            api_key (str, optional): API key to be initiliazed and validated. Defaults to None.

        Returns:
            dict: API key validation response from Server. Example: {'success': True, 'error': None}
        """        
        self.api_key = api_key or self.api_key
        url = f'{self.api_server_url}validate_key'
        params = {'version': Aki.get_version(), 'key': self.api_key}
        self.setup_session(session)
        try:
            async with self.session.get(url=url, params=params) as response:
                response_json = await response.json()
                if response.status == 200:
                    return response_json
                else:
                    return await self.__handle_error_async(response, 'key_validation')

        except aiohttp.client_exceptions.ClientConnectorError as exception:
            return await self.__handle_error_async(None, 'key_validation', exception=exception)


    def init_api_key(self, api_key=None):
        """Initialize and validate api key.

        Args:
            api_key (str, optional): API key to be initiliazed and validated. Defaults to None.

        Returns:
            dict: API key validation response from Server. Example: {'success': True, 'error': None}
        """
        self.api_key = api_key or self.api_key
        url = f'{self.api_server_url}validate_key'
        params = {'version': Aki.get_version(), 'key': self.api_key}
        try:
            response = requests.get(url=url, params=params)

            if response.status_code == 200:
                return response.json()
            else:
                return self.__handle_error_sync(response, 'key_validation')

        except requests.exceptions.ConnectionError as exception:
            return self.__handle_error_sync(None, 'key_validation', exception=exception)


    @staticmethod
    def encode_binary(binary_data, media_format='octet-stream', media_type=None):
        media_format = media_format.lower()
        if not media_type:
            media_type = Aki.detect_media_type_from_media_format(media_format)
        return f'data:{media_type}/{media_format};base64,' + base64.b64encode(binary_data).decode('utf-8')


    @staticmethod
    def decode_binary(base64_data):
        if isinstance(base64_data, str):
            if base64_data.startswith('data:'):
                header, base64_raw_data = base64_data.split(',')
                mime_type = re.match(r'^data:([^/]+)/([^;,]+)?;base64?', header)
                return mime_type.group(2).lower(), base64.b64decode(base64_raw_data.encode('utf-8'))
            else:
                # has no data: header, unknown media type
                binary_string = base64.b64decode(base64_data.encode('utf-8'))
                return 'octet-stream', binary_string

        if isinstance(base64_data, bytes):
            return 'octet-stream', base64_data # already binary, can not detect media type

        return None, None # unknown data type, can not decode

    async def do_api_request_async(
        self,
        params,
        result_callback=None,
        progress_callback=None,
        progress_interval=DEFAULT_PROGRESS_INTERVAL,
        session=None,
        ):
        """
        Do an asynchronous API request with optional progress data via asynchronous or synchronous callbacks. 

        Args:
            params (dict): Dictionary with parameters for the the API request like 'prompt' or 'image'.
            result_callback (callable or coroutine, optional): Callback function or coroutine with argument result (dict) to handle the API request result.
                Accepts synchronous functions and asynchrouns couroutines. Defaults to None
            progress_callback (callable or coroutine, optional): Callback function or coroutine with arguments progress_info (dict) and 
                progress_data (dict) for tracking progress. Accepts synchronous functions and asynchrouns couroutines. Default is None.
            progress_interval (int, optional): Interval in seconds at which progress is checked. Default is {DEFAULT_PROGRESS_INTERVAL}.
            session (aiohttp.ClientSession): Give existing session to Aki API to make login request in given session. Defaults to None.

        Raises:
            ConnectionError: Raised if client couldn't connect with API server and raise_exceptions is True. 
            BrokenPipeError: Raised if client lost connection during transmitting and raise_exceptions is True.
            ValueError: Raised if request contains invalid input parameters and raise_exceptions is True.
            PermissionError: Raised if request contains invalid api key and raise_exceptions is True.

        Returns:
            dict: Dictionary with job results

        Examples:

            Example job result dict in result callback argument or return value:

            .. highlight:: python
            .. code-block:: python

                result = {
                    'auth': '<name_of_worker>',
                    'compute_duration': 2.4,
                    'images': 'data:image/PNG;base64,...'
                    'job_id': 'JID3',
                    'seed': 1234413214,
                    'success': True,
                    'text': 'Test output...',
                    'total_duration': 2
                }

                
            Example progress_callback arguments progress_info and progress_data dictionaries:  

            .. highlight:: python
            .. code-block:: python
            

                progress_info = {
                    'job_id': 'JID3', 
                    'progress': 50, 
                    'queue_position': 0, 
                    'estimate': -1
                }
                progress_data = {
                        'info': '<infos from worker about progress', 
                        'images': 'data:image/PNG;base64,...',
                        'text': 'Test outpu...'
                }
        """
        self.setup_session(session)
        url = f'{self.api_server_url}call/{self.endpoint_name}'
        params['client_session_auth_key'] = self.client_session_auth_key
        params['key'] = self.api_key
        params['wait_for_result'] = not progress_callback
        result = await self.__fetch_async(url, params)
        if progress_callback:
            if result.get('success'):
                job_id = result['job_id']
                init_progress_info = {
                    'job_id': job_id, 
                    'progress': 0, 
                    'queue_position': -1, 
                    'estimate': -1
                }
                await if_async_else_run(progress_callback, init_progress_info, None)
                return await self.__finish_api_request_while_receiving_progress_async(
                    job_id,
                    result_callback,
                    progress_callback,
                    progress_interval
                )
            else:
                await if_async_else_run(result_callback, result)
        else:
            result = self.__convert_result_params(result)
            await if_async_else_run(result_callback, result)

        return result


    async def get_api_request_generator(
        self,
        params,
        progress_interval=DEFAULT_PROGRESS_INTERVAL,
        session=None
        ):
        """Generator function to get request generator, yielding the results

        Args:
            params (dict): Dictionary with parameters for the the API request.
            progress_interval (int, optional): Interval in seconds at which progress is checked. Defaults to DEFAULT_PROGRESS_INTERVAL.
            session (aiohttp.ClientSession): Give existing session to Aki API to make login request in given session. Defaults to None.

        Yields:
            dict: Result dictionary containing job and progress results.

        Example usage:

            .. highlight:: python
            .. code-block:: python

                output_generator = model_api.get_api_request_generator()
                async for output in output_generator:
                    process_output(output)
                    print(output)

        Example output:

            .. highlight:: python
            .. code-block:: python

                {
                    'job_id': 'JID01',
                    'success': True,
                    'job_state': 'started'
                },
                {
                    'job_id': 'JID01',
                    'success': True,
                    'job_state': 'processing'
                    'progress': 55,                   
                    'queue_position': 0,
                    'estimate': 43.6
                    'progress_data': {
                        'text': 'Example generated text',
                        'num_generated_tokens': 55,
                        'current_context_length': 116,
                    },
                },
                ...
                {
                    'job_id': 'JID01',
                    'success': True,
                    'job_state': 'done',
                    'progress': 100,
                    'result_data': {
                        'text': 'Example generated final text',
                        'num_generated_tokens': 100,
                        'current_context_length': 116,
                        'max_seq_len': 8000,
                        'prompt_length': 17,
                        'ep_version': 2,
                        'model_name': 'Llama-3-3-70B-Instruct-fp8',
                        'result_sent_time': 1736785517.1456308,
                        'compute_duration': 16.8,
                        'total_duration': 17.0,
                        'start_time': 1736785499.9088438,
                        'start_time_compute': 1736785500.1000788,
                        'pending_duration': 0.0004534721374511719,
                        'preprocessing_duration': 0.07061362266540527,
                        'arrival_time': 1736785500.1973228,
                        'finished_time': 1736785516.8741965,
                        'result_received_time': 1736785516.9283218
                    }
                }            
        """        
        self.setup_session(session)
        params['client_session_auth_key'] = self.client_session_auth_key
        params['key'] = self.api_key
        params['wait_for_result'] = False
        result = await self.__fetch_async(f'{self.api_server_url}call/{self.endpoint_name}', params)
        if result.get('success'):
            job_id = result['job_id']
            yield {
                'job_id': job_id,
                'success': result.get('success'),
                'job_state': 'started'
            }
            job_done = False
            now = time.time()
            while not job_done:
                progress_result = await self.__fetch_progress_async(job_id)
                if progress_result:
                    job_state = progress_result.get('job_state')
                    job_done = job_state in ('done', 'lapsed') and not progress_result.get('progress', {})
                    result, progress_data = self.__process_progress_result(progress_result)
                    result[f'{"result" if job_done else "progress"}_data'] = progress_data
                    if job_state != 'canceled':
                        yield result
                        await asyncio.sleep(progress_interval)
        else:
            yield result


    def do_api_request(
        self,
        params,
        progress_callback=None,
        progress_interval=DEFAULT_PROGRESS_INTERVAL
        ):
        """
        Do an synchronous API request with optional progress data via callbacks. 

        Args:
            params (dict): Dictionary with parameters for the the API request like 'prompt' or 'image'
            progress_callback (callable, optional): Callback function or coroutine with arguments  progress_info (dict) and 
                progress_data (dict) for receiving progress data. Defaults to None.
            progress_interval (int, optional): Interval in seconds at which progress is checked. Default is 300.

        Raises:
            ConnectionError: Raised if client couldn't connect with API server and raise_exceptions is True. 
            BrokenPipeError: Raised if client lost connection during transmitting and raise_exceptions is True.
            ValueError: Raised if request contains invalid input parameters and raise_exceptions is True.
            PermissionError: Raised if request contains invalid api key and raise_exceptions is True.

        Returns:
            dict: Dictionary with request result parameters.

        Examples:

            Example job result dict:

            .. highlight:: python
            .. code-block:: python

                result = {
                    'auth': '<name_of_worker>',
                    'compute_duration': 2.4,
                    'images': 'data:image/PNG;base64,...'
                    'job_id': 'JID3',
                    'seed': 1234413214,
                    'success': True,
                    'text': 'Test output...',
                    'total_duration': 2
                }

                
            Example progress_callback arguments progress_info and progress_data dictionaries:          

            .. highlight:: python
            .. code-block:: python

                progress_info = {
                    'job_id': 'JID3', 
                    'progress': 50, 
                    'queue_position': 0, 
                    'estimate': -1
                }
                progress_data = {
                        'info': '<infos from worker about progress', 
                        'images': 'data:image/PNG;base64,...',
                        'text': 'Test outpu...'
                }

        """
        url = f'{self.api_server_url}call/{self.endpoint_name}'
        params['client_session_auth_key'] = self.client_session_auth_key
        params['key'] = self.api_key
        params['wait_for_result'] = not progress_callback
        result = self.__fetch_sync(url, params)
        if progress_callback:
            if result.get('success'):
                job_id = result['job_id']
                init_progress_info = {
                    'job_id': job_id,
                    'progress': 0,
                    'queue_position': -1,
                    'estimate': -1
                }
                progress_callback(init_progress_info, None)
                result = self.__finish_api_request_while_receiving_progress_sync(
                    job_id,
                    progress_callback,
                    progress_interval
                )
        else:
            result = self.__convert_result_params(result)

        return result


    def append_progress_input_params(self, job_id, input_parameters):
        if job_id in self.progress_input_params:
            self.progress_input_params[job_id].append(input_parameters)
        else:
            self.progress_input_params[job_id] = [input_parameters]


    def cancel_request(self, job_id=None):
        self.canceled_jobs.append('all')


    def get_endpoint_list(self, api_key=None):
        """Retrieve list of all available endpoints. If API key is given, only the endpoints with request permission are listed.

        Args:
            api_key (str, optional): The AKI api_key to show also endpoints only available to keys with special permission. Defaults to None.

        Returns:
            list[str]: List containing the endpoint names.
        """        
        try:
            response = requests.get(
                url=f'{self.api_server_url}endpoints',
                params={
                    'key': api_key
                }
            )
            if response.status_code == 200:
                return response.json().get('endpoints')
            else:
                return self.__handle_error_sync(response, 'get_endpoint_list')

        except requests.exceptions.ConnectionError as exception:
            return self.__handle_error_sync(None, 'get_endpoint_list', exception=exception)


    def get_endpoint_details(self, endpoint_name, api_key=None):
        """Get endpoint details about given endpoint name.

        Args:
            endpoint_name (str): Name of the endpoint

        Returns:
            dict: Detailed information about the given endpoint.

        Examples:

            .. highlight:: python
            .. code-block:: python

                endpoint_details = {
                    'name': 'llama3_chat',
                    'title': 'LLama 3.x Chat',
                    'description': 'Llama 3.x Instruct Chat example API',
                    'http_methods': ['GET', 'POST'],
                    'version': 2,
                    'max_queue_length': 1000,
                    'max_time_in_queue': 3600,
                    'free_queue_slots': 1000,
                    'category': 'chat', 
                    'num_active_workers': 1,
                    'num_workers': 1,
                    'workers': [
                        {
                            'name': 'hostname#0_2xNVIDIA_GeForce_RTX_3090',
                            'state': 'waiting',
                            'max_batch_size': 128,
                            'free_slots': 128,
                            'gpu_name': NVIDIA_GeForce_RTX_3090,
                            'num_gpus': 2,
                            'model': {
                                'label': 'Meta-Llama-3-8B-Instruct',
                                'quantization': 'fp16',
                                'size': '8B',
                                'family': 'Llama',
                                'type': 'LLM',
                                'repo_name': 'Meta-Llama-3-8B-Instruct'
                            }
                        }
                    ],
                    'parameter_description': {
                        'input': {
                            'prompt_input': {
                                'type': 'string',
                                'default': '',
                                'required': False
                            }, 
                            'chat_context': {
                                'type': 'json',
                                'default': '',
                                'required': False
                                }, 
                            'top_k': {
                                'type': 'integer',
                                'minimum': 1,
                                'maximum': 1000,
                                'default': 40
                            },
                            'top_p': {
                                'type': 'float',
                                'minimum': 0.0,
                                'maximum': 1.0,
                                'default': 0.9
                            },
                            'temperature': {
                                'type': 'float',
                                'minimum': 0.0,
                                'maximum': 1.0,
                                'default': 0.8
                            }, 
                            'max_gen_tokens': {
                                'type': 'integer',
                                'default': 2000
                            },
                            'wait_for_result': {
                                'type': 'bool'
                            }
                        }, 
                        'output': {
                            'text': {
                                'type': 'string'
                            },
                            'num_generated_tokens': {
                                'type': 'integer'
                            },
                            'model_name': {
                                'type': 'string'
                            },
                            'max_seq_len': {
                                'type': 'integer'
                            },
                            'current_context_length': {
                                'type': 'integer'
                            },
                            'error': {
                                'type': 'string'
                            },
                            'prompt_length': {
                                'type': 'integer'
                            }
                        },
                        'progress': {
                            'OUTPUTS': {
                                'text': {
                                    'type': 'string'
                                },
                                'num_generated_tokens': {
                                    'type': 'integer'
                                },
                                'current_context_length': {
                                    'type': 'integer'
                                }
                            }
                        }
                    }
                }
        """        
        try:
            response = requests.get(
                url=f'{self.api_server_url}endpoints/{endpoint_name}',
                params={'key': api_key or self.api_key}
            )
            if response.status_code == 200:
                return response.json()
            else:
                return self.__handle_error_sync(response, 'get_endpoint_details')

        except requests.exceptions.ConnectionError as exception:
            return self.__handle_error_sync(None, 'get_endpoint_details', exception=exception)


    def setup_session(self, session):
        """Open a new session if session is

        Args:
            session (aiohttp.ClientSession): Give existing session to Aki API to make upcoming requests in given session.
        """        
        if session:
            self.session = session
        elif not self.session or self.session.closed:
            self.session = aiohttp.ClientSession()

    async def close_session(self):
        """
        Close the aiohttp client session saved in Aki().session.
        """
        if self.session:
            await self.session.close()


    @staticmethod
    def detect_media_type_from_media_format(media_format):

        IMAGE_FORMATS = {'png', 'jpeg', 'webp', 'tiff', 'gif', 'bmp'}
        AUDIO_FORMATS = {'wav', 'mp3', 'ogg', 'flac'}

        if media_format:
            media_format = media_format.lower()

            if media_format in IMAGE_FORMATS:
                return 'image'
            if media_format in AUDIO_FORMATS:
                return 'audio'

        return 'octet-stream'

    @staticmethod
    def check_if_valid_base64_string(test_string):
        """
        Check if given string is a valid base64-encoded string.

        Args:
            test_string (str): The string to test.

        Returns:
            bool: True if the string is a valid base64-encoded string, False otherwise.
        """
        try:
            body = test_string.split(',')[1] if ',' in test_string else None
            return base64.b64encode(base64.b64decode(body.encode('utf-8'))).decode('utf-8') == body if body else False
        except (TypeError, base64.binascii.Error, ValueError):
            return False


    __package_version = None # will be set by get_version()


    @staticmethod
    def get_version():
        """Get name and package version of AKI.IO Client Interface

        Returns:
            str: Name and version of AKI.IO Interface
        """

        if not Aki.__package_version:
            from pathlib import Path
            setup_py = Path(__file__).resolve().parent.parent / 'setup.py'
            with open(setup_py, 'r') as file:                
                version_no = re.search(r"version\s*=\s*'(.*)'\s*,\s*\n", file.read()).group(1)
            Aki.__package_version = f'Python AKI.IO Client {version_no}'
        
        return Aki.__package_version


    async def __finish_api_request_while_receiving_progress_async(
        self,
        job_id,
        result_callback,
        progress_callback,
        progress_interval
        ):
        """
        Finish the asynchronous API request while receiving progress data every progress_interval=300 seconds.

        Args:
            job_id (str): ID of related job.
            result_callback (callable or coroutine, optional): Callback function or coroutine with argument result (dict) 
                to handle the API request result. Accepts synchronous functions and asynchronous couroutines. Defaults to None
            progress_callback (callable or coroutine, optional): Callback function or coroutine with arguments progress_info (dict) and 
                progress_data (dict) for tracking progress. Accepts synchronous functions and asynchronous couroutines. Default is None.
            progress_interval (int): Interval in seconds at which progress is checked.
        """ 
        job_done = False
        while not job_done:
            progress_result = await self.__fetch_progress_async(job_id)
            job_state = progress_result.get('job_state')
            job_done = job_state in ('done', 'canceled', 'lapsed') and not progress_result.get('progress', {})
            progress_info, progress_data = self.__process_progress_result(progress_result)            
            if not job_done:
                await if_async_else_run(progress_callback, progress_info, progress_data)
                await asyncio.sleep(progress_interval)
        await if_async_else_run(result_callback, progress_data)
        return progress_data


    def __finish_api_request_while_receiving_progress_sync(
        self,
        job_id,
        progress_callback,
        progress_interval
        ):
        """
        Finish the API request while receiving progress data every progress_interval=300 seconds.

        Args:
            job_id (str): ID of related job.
            progress_callback (callback): Callback function with arguments progress_info (dict) and progress_data (dict) 
                for tracking progress.
            progress_interval (int): Interval in seconds at which progress is checked.

        Returns:
            dict: Dictionary with job results
        """ 
        job_done = False
        while not job_done:         
            progress_result = self.__fetch_progress_sync(job_id)
            job_state = progress_result.get('job_state')
            job_done = job_state in ('done', 'canceled', 'lapsed') and not progress_result.get('progress', {})
            progress_info, progress_data = self.__process_progress_result(progress_result)
            if not job_done:
                progress_callback(progress_info, progress_data)
                time.sleep(progress_interval)
        return progress_data
        

    def __process_progress_result(self, progress_result):
        """Format received progress results depending on job state and self.output_binary_format.

        Args:
            progress_result (dict): Progress result dictionary received from API server

        Returns:
            dict, dict: Dictionaries progress_info and progress_result.
        """
        progress_info = {'job_id': progress_result.get('job_id')}
        if progress_result.get('success'):
            job_state = progress_result.get('job_state')
            if job_state in ('done', 'canceled') and not progress_result.get('progress'):
                progress_data = progress_result.get('job_result', {})
                if job_state == 'canceled':
                    progress_data['job_state'] = job_state
                progress_data['job_id'] = progress_info['job_id']
                progress_info['success'] = progress_result.get('success')
                progress_info['job_state'] = job_state
                progress_info['progress'] = 100
            else:
                progress = progress_result.get('progress', {})
                progress_info['progress'] = progress.get('progress')
                progress_info['queue_position'] = progress.get('queue_position')
                progress_info['estimate'] = progress.get('estimate')
                progress_data = progress.get('progress_data', {})
                progress_info['job_state'] = job_state
                progress_info['success'] = progress_result.get('success')

            return progress_info, self.__convert_result_params(progress_data)

        else:
            return progress_info, progress_result


    async def __fetch_async(
        self,
        url,
        params,
        do_post=True,
        result_callback=None
        ):
        """
        Perform an asynchronous HTTP request to the API server. 
        Python objects and byte string params will be converted automatically to base64 string.
        Binary data in the result data will be converted to self.output_binary_format set in the Aki constructor.

        Args:
            url (str): The URL for the HTTP request.
            params (dict): Parameters for the HTTP request.
            do_post (bool, optional): Whether to use a POST request. Defaults to True.

        Returns:
            dict: The result from the API worker via API server.

        """
        params = self.__serialize_json_values(params)
        error = self.__check_params_encoded(params)
        if error:
            response = await self.__handle_error_async(error, 'api')
            return response
        try:       
            method = self.session.post if do_post else self.session.get
            request_params = {'json': params} if do_post else {'params': params}

            async with method(url, **request_params) as response:
                response_json = await response.json()
                if response.status == 200:
                    return response_json
                else:
                    response = await self.__handle_error_async(response, 'api')
                    if result_callback:
                        await if_async_else_run(result_callback, response)
                    return response
                        
        except aiohttp.client_exceptions.ClientConnectorError as exception:
            response = await self.__handle_error_async(None, 'api', exception=exception)
            if result_callback:
                await if_async_else_run(result_callback, response)
            return response


    def __fetch_sync(
        self,
        url,
        params,
        do_post=True
        ):
        """
        Perform a synchronous HTTP request to the API server. Python objects and byte string params will be converted automatically to base64 string.
        Base64 strings containing in the result will be converted back to python objects or to byte strings.

        Args:
            url (str): The URL for the HTTP request.
            params (dict): Parameters for the HTTP request.
            do_post (bool, optional): Whether to use a POST request. Defaults to True.

        Returns:
            dict: The result from the API worker via API server.

        """
        params = self.__serialize_json_values(params)
        error = self.__check_params_encoded(params)
        if error:
            return self.__handle_error_sync(error, 'api')
        try:
            method = requests.post if do_post else requests.get
            request_params = {'json': params} if do_post else {'params': params}

            response = method(url, **request_params)
            

            if response.status_code == 200:
                return response.json()
            else:
                return self.__handle_error_sync(response, 'api')
      
        except requests.exceptions.ConnectionError as exception:
            return self.__handle_error_sync(None, 'api', exception=exception)


    async def __fetch_progress_async(self, job_id):
        """
        Fetch progress data asynchronously from API server for running job with given job id.

        Args:
            job_id (str): Job id of running job

        Returns:
            dict: Dictionary with progress result of the job.

        """
        counter = 0
        while True:
            counter += 1
            try:
                url = f'{self.api_server_url}progress/{self.endpoint_name}'
                if job_id in self.canceled_jobs:
                    canceled = True
                    self.canceled_jobs.remove(job_id)
                elif 'all' in self.canceled_jobs:
                    canceled = True
                    self.canceled_jobs.remove('all')
                else:
                    canceled = False
                params = {
                    'key': self.api_key, 
                    'job_id': job_id,
                    'canceled': canceled
                }
                
                async with self.session.post(url, json=params) as response:
                    response_json = await response.json()
                    if response.status == 200:
                        return response_json
                    else:
                        if counter > 3:
                            return await self.__handle_error_async(response, 'progress')
                        else:
                            await asyncio.sleep(1)
                            continue

            except aiohttp.client_exceptions.ClientConnectionError as exception:
                if counter > 3:
                    return await self.__handle_error_async(None, 'progress', exception=exception)
                else:
                    await asyncio.sleep(1)
                    continue


    def __fetch_progress_sync(self, job_id):
        """
        Fetch progress data from API server for running job with given job id.

        Args:
            job_id (str): Job id of running job.

        Returns:
            dict: Progress information for the job.

        """

        url = f'{self.api_server_url}progress/{self.endpoint_name}'
        params = {
            'key': self.api_key, 
            'job_id': job_id
        }
        params.update(self.progress_input_params.pop(job_id, {}))
        if job_id in self.canceled_jobs:
            self.canceled_jobs.remove(job_id)
            params['cancel'] = True
        try:
            response = requests.post(url, json=params)
        except requests.exceptions.ConnectionError as exception:
            return self.__handle_error_sync(None, 'progress', exception=exception)
        
        if response.status_code == 200:
            return response.json()
        else:
            return self.__handle_error_sync(response, 'progress')


    async def __handle_error_async(
        self,
        response,
        request_type,
        exception=None
        ):
        """Asynchronous error handler. Calls error_callback if given with error_description as argument, else raises ConnectionError.

        Args:
            response (requests.models.Response): Response of http request
            request_type (str): Type of request (login, API request, progress)

        Raises:
            ConnectionError: ConnectionError with error description.
            PermissionError: Raised if client is not logged in to the API server.

        Returns:
            str: Error description
        """
        if self.session and not self.session.close:
            await self.session.close()

        if hasattr(response, 'json'):
            response_json = await response.json()
            error_msg = response_json.get('error')
            if isinstance(error_msg, list):
                response_json['error'] = ';'.join(error_msg)
            if 'error' not in response_json.keys():
                response_json['error'] = 'Unknown network error'
            if 'success' not in response_json.keys():
                response_json['success'] = False
        else:
            response_json = {
                'success': False,
                'error': str(exception) if exception else str(response) # To catch unknown network response types
            }

        status_code = response.status if hasattr(response, 'status') else 400 # else bad request


        response_json['error_code'] = status_code
        if self.raise_exceptions:
            self.__raise_exception(response_json, request_type, status_code)
        response_json['error_code'] = status_code
        return response_json

    def __handle_error_sync(
        self,
        response,
        request_type,
        exception=None
        ):
        if hasattr(response, 'json'):
            response_json = response.json()
            error_msg = response_json.get('error')
            if isinstance(error_msg, list):
                response_json['error'] = ';'.join(error_msg)
            if 'error' not in response_json.keys():
                response_json['error'] = 'Unknown network error'
            if 'success' not in response_json.keys():
                response_json['success'] = False

        elif hasattr(response, 'text'):
            response_json = {
                'success': False,
                'error': response.text
            }
        else:
            response_json = {
                'success': False,
                'error': str(exception) if exception else str(response) # To catch unknown network response types
            }
        status_code = response.status_code if hasattr(response, 'status_code') else 400 # else bad request
        
        if self.raise_exceptions:
            self.__raise_exception(response_json, request_type, status_code)
        response_json['error_code'] = status_code
        return response_json


    def __raise_exception(self, response_json, request_type, status_code):
        error_description = self.__make_error_description(
            response_json,
            request_type,
            status_code
        )
        if 500 <= status_code < 600 or status_code == 404: # 404 = Endpoint disabled or no free queue slot
            if request_type == 'progress':
                raise BrokenPipeError(error_description)
            else:
                raise ConnectionError(error_description)
        elif status_code == 400:
            raise ValueError(error_description)
        elif 400 < status_code < 500:
            raise PermissionError(error_description)


    def __make_error_description(
        self, 
        response_json, 
        request_type,
        status_code
        ):
        """Helper method to create error report string

        Args:

            response_json (dict): Request error response dict.
            request_type (str): Type of request (login, api, progress)
            status_code (int): Status code of request response.

        Returns:
            str: Error description
        """
        status_code_str = f'Http status code: {status_code}\nError message: {response_json.get("error")}'
        return f'{request_type.capitalize()} request at {self.api_server_url} failed!\n{status_code_str}'



    def __convert_result_params(self, params):
        """
        Converts base64-encoded parameters to byte string data in given dictionary.

        Args:
            params (dict): Dictionary of parameters.

        Returns:
            dict: Dictionary with base64-encoded parameters converted to output format defined in self.output_binary_format.
        """
        params_converted = dict()
        if params and isinstance(params, dict):
            for key, value in params.items():
                if isinstance(value, list):
                    value = [self.__convert_base64_to_desired_format(base64_string) for base64_string in value]
                elif isinstance(value, str):
                    value = self.__convert_base64_to_desired_format(value)
                    
                params_converted[key] = value
        elif params and isinstance(params, list):
            params_converted = [self.__convert_result_params(params_chunk) for params_chunk in params]
        return params_converted


    def __convert_base64_to_desired_format(self, value):
        """Convert given base64 string to byte-string if outputformat == byte-string.

        Args:
            value (str): Base64 string to be converted

        Returns:
            str, bytes or object: Base64 string bytes string or python object, depending on self.output_binary_format.
        """
        if self.output_binary_format == 'byte_string':
            if Aki.check_if_valid_base64_string(value):
                return base64.b64decode(value.split(',')[1].encode('utf-8'))
        else:
            return value

    def __check_params_encoded(self, params):
        """
        Convert byte string data parameters to base64 encoding in a dictionary.

        Args:
            params (dict): Dictionary of parameters.

        Returns:
            dict: Dictionary with byte string data parameters converted to base64 encoding.
        """
        if params:
            for key, value in params.items():
                if isinstance(value, bytes):
                    return f"param '{key}' is in binary form, please use Aki.encode_binary(...) to set binary data"
        return None


    def __serialize_json_values(self, params):
        if params:
            for key, value in params.items():
                if isinstance(value, dict) or isinstance(value, list):
                    params[key] = json.dumps(value)
        return params


async def do_aki_request_async(
    endpoint_name, 
    api_key,
    params,
    result_callback = None, 
    progress_callback = None,
    session = None
    ):
    """
    A simplified interface for making a single asynchronous API request with do_api_login included.

    Args:
        endpoint_name (str): The name of the API endpoint
        api_key (str): The api_key, register for your AKI api key at https://aki.io
        params (dict): Parameters for the API request
        result_callback (callback, optional): Callback function with argument result (dict) to handle the API request result. Defaults to None.
        progress_callback (callback, optional): Callback function with arguments progress_info (dict). Defaults to None.
            and progress_data (dict) for tracking progress. Defaults to None.
        session (aiohttp.ClientSession): Give existing session to Aki API to make login request in given session. Defaults to None.

    Returns:
        dict: Dictionary with request result parameters.

    Raises:
        ConnectionError: Raised if client couldn't connect with API server and no request_error_callback is given. Also raised if client lost connection during transmitting
            and no progress_error_callback is given.

    Examples:

        Example usage with synchronous callbacks:

        .. highlight:: python
        .. code-block:: python

            import asyncio

            def result_callback(result):
                process_result(result)

            def progress_callback(progress_info, progress_data):
                process_progress_info(progress_info)
                process_progress_data(progress_data)


            asyncio.run(do_api_request('llama3_chat', {'text': 'Chat question'}, 'api_key', result_callback, progress_callback))

        Example usage with asynchronous callbacks:

        .. highlight:: python
        .. code-block:: python

            import asyncio

            async def result_callback(result):
                await process_result(result)

            async def progress_callback(progress_info, progress_data):
                await process_progress_info(progress_info)
                await process_progress_data(progress_data)


            result = asyncio.run(do_api_request('llama3_chat', {'text': 'Chat question'}, 'api_key', result_callback, progress_callback))


        Example progress result dictionary at start:

        .. highlight:: python
        .. code-block:: python

            progress_result = {
                'job_id': 'JID6',
                'job_state': 'processing',
                'progress': {
                    'progress': 0, 
                    'queue_position': 0
                },
                'success': True
            }

        Example progress result dictionary while processing:

        .. highlight:: python
        .. code-block:: python

            progress_result = {
                'job_id': 'JID6',
                'job_state': 'processing',
                'progress': {
                    'job_id': 'JID6', 
                    'progress': 50,
                    'progress_data': {
                        'images': 'base64-string',
                        'text': 'Test outpu'
                    },
                    'queue_position': 0
                },
                'success': True
            }

        Example progress_result dictionaries when finished:

        .. highlight:: python
        .. code-block:: python

            progress_result = {
                'job_id': 'JID6',
                'job_result': {
                    'auth': 'neo07_GPU0',
                    'compute_duration': 2.4,
                    'images': 'data:image...',
                    'text': 'Test outpu...',
                    'total_duration': 2.5
                },
                'job_state': 'done',
                'progress': {
                    'job_id': 'JID6',
                    'progress': 100,
                    'progress_data': {
                        'images': 'data:image...',
                        'text': 'Test outpu...'
                    },
                    'queue_position': 0
                },
                'success': True
            }
    """

    aki = Aki(endpoint_name, api_key, session)
    result = await aki.do_api_request_async(
        params,
        result_callback,
        progress_callback
    )
    return result


def do_aki_request(
    endpoint_name,
    api_key,
    params,
    progress_callback = None
    ):
    """A simplified interface for making a single synchronous API request

    Args:
        endpoint_name (str): Name of endpoint
        api_key (str): The api_key, register for your AKI api key at https://aki.io
        params (dict): Dictionary with api request parameters
        progress_callback (callback, optional): Callback function with arguments progress_info (dict) 
            and progress_data (dict) for tracking progress. Defaults to None.

    Returns:
        dict: Dictionary with request result parameters

    Raises:
        ConnectionError: Raised if client couldn't connect with API server. Also raised if client lost connection during transmitting
            and no progress_error_callback is given.


    Examples:

        Example usage with progress_callback:

        .. highlight:: python
        .. code-block:: python

            import asyncio

            def progress_callback(progress_info, progress_data):
                process_progress_info(progress_info)
                process_progress_data(progress_data)


            result = do_aki_request('llama3_chat', 'api_key', {'text': 'Chat question'}, progress_callback)
        
        Example progress result dictionary at start:

        .. highlight:: python
        .. code-block:: python

            progress_result = {
                'job_id': 'JID6',
                'job_state': 'processing',
                'progress': {
                    'progress': 0, 
                    'queue_position': 0
                },
                'success': True
            }

        Example progress result dictionary while processing:

        .. highlight:: python
        .. code-block:: python

            progress_result = {
                'job_id': 'JID6',
                'job_state': 'processing',
                'progress': {
                    'job_id': 'JID6', 
                    'progress': 50,
                    'progress_data': {
                        'images': 'base64-string',
                        'text': 'Test outpu'
                    },
                    'queue_position': 0
                },
                'success': True
            }

        Example progress_result dictionaries when finished:

        .. highlight:: python
        .. code-block:: python

            progress_result = {
                'job_id': 'JID6',
                'job_result': {
                    'auth': 'neo07_GPU0',
                    'compute_duration': 2.4,
                    'images': 'data:image...',
                    'text': 'Test outpu...',
                    'total_duration': 2.5
                },
                'job_state': 'done',
                'progress': {
                    'job_id': 'JID6',
                    'progress': 100,
                    'progress_data': {
                        'images': 'data:image...',
                        'text': 'Test outpu...'
                    },
                    'queue_position': 0
                },
                'success': True
            }
    """
    aki = Aki(endpoint_name, api_key)
    return aki.do_api_request(params, progress_callback)
    
    
async def if_async_else_run(callback, *args):    
    """Helper method to either await asynchronous coroutine or call synchronous functions.

    Args:
        callback (function or coroutine): Await asynchronous coroutine, call synchronous functions.

    Returns:
        callback(*args): Result of callback
    """    
    if asyncio.iscoroutinefunction(callback):
        return await callback(*args)
    elif callable(callback):
        return callback(*args)
