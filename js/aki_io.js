// AKI.IO Model Hub API Javascript interface
//
// Copyright (c) AKI.IO GmbH and affiliates. Find more info at https://aki.io
//
// This software may be used and distributed according to the terms of the MIT LICENSE

/**
 * Class for communication with the AKI.IO Model API.
 * @class
 */
class Aki {

    static version = 'JavaScript AKI.IO API Client Interface 1.0.0';
    
    /**
    * Constructor of the class.
    * @constructor
    * @param {string} endpointName - The name of the API endpoint.
    * @param {sting} apiKey - api Key required to authenticate and authorize to use api endpoint    * 
    * @param {object} options {
    *           progressIntervall: 300, - The intervall for progress updates in milliseconds. The default progress update intervall is 300.
    *           apiServerUrl: 'https://aki.io/api/', - set URL to specific API servers 
    *        }
    */
    constructor(endpointName, apiKey, options = {}) {
        this.endpointName = endpointName;        
        this.apiKey = apiKey;
        this.clientSessionAuthKey = null;
        this.apiServerUrl = 'https:/aki.io/api/';
        this.defaultProgressIntervall = 300;
        this.jobsCanceled = {};
        this.progressInputParams = {};

        if( typeof options == 'object')
        {
            if( options['apiServerUrl'] ) {
                this.apiServerUrl = options['apiServerUrl'];
                if(!this.apiServerUrl.endsWith('/'))
                {
                    this.apiServerUrl += "/";
                }
            }
            if( options['progressIntervall'] ) {
                this.defaultProgressIntervall = options['progressIntervall'];
            }
        }
    }

    /**
     * Method for asynchronous HTTP requests (GET and POST).
     * @async
     * @param {string} url - The URL of the request.
     * @param {Object} params - The parameters of the request.
     * @param {boolean} [doPost=true] - Specifies whether the request is a POST request (default: true).
     * @returns {Object} - The JSON data of the response.
     */
    async fetchAsync(url, params, doPost = true) {
        const method = doPost ? 'POST' : 'GET';
        const headers = doPost ? { 'Content-type': 'application/json; charset=UTF-8' } : {};
        const body = doPost ? JSON.stringify(params) : null;

        const response = await fetch(url, { method, headers, body });

        //if (!response.ok) {
        //    throw new Error(`Failed to fetch data from ${url}. Response ${response}`);
        //}

        return response.json();
    }

    /**
     * Method for API Key Initialization.
     * @async
     * @param {function} [resultCallback=null] - The callback after successful API key validation.
     * @param {function} [resultCallback=null] - The error callback for unsuccessful API key validation.
     * @param {sting} apiKey - optional: set to new api Key required to authenticate and authorize to use api endpoint
     * 
     */
    async initAPIKey(resultCallback = null, errorCallback = null, apiKey = null) {
        if (apiKey != null) {
            this.apiKey = apiKey;
        }
        try {
                const response = await this.fetchAsync(
                    `${this.apiServerUrl}validate_key?key=${this.apiKey}&version=${encodeURIComponent(Aki.version)}`, false
                );
                if (response.success) {
                    if (resultCallback && typeof resultCallback === 'function') {
                        resultCallback(response);
                    }
                    else {
                        console.log(`API Key initialized`);
                    }
                    return response;
                }
                else {
                    var errorMessage = `${response.error}`
                    if (response.ep_version) {
                        errorMessage += ` Endpoint version: ${response.ep_version}`
                    }
                    throw new Error(errorMessage)
                }
            } 
        catch (error) {
            console.error('Error during API Key Validation:', error);
            if (errorCallback && typeof errorCallback === 'function') {
                errorCallback(error);
            }
        }
    }


    /**
     * Method for API requests with options for progress updates.
     * @async
     * @param {Object} params - The parameters of the API request.
     * @param {function} resultCallback - The callback after the request is completed.
     * @param {function} [progressCallback=null] - The callback for progress updates.
     * @param {boolean} [progressStream=false] - Specifies whether the progress should be streamed (default: false). Attention: The stream feature is not yet fully implemented.
     */
    async doAPIRequest(params, resultCallback, progressCallback = null, progressStream = false, apiKey = null) {
        const url = `${this.apiServerUrl}call/${this.endpointName}`;
        console.log(`URL: ${url}`);

        params = await this.stringifyObjects(params)
        params.client_session_auth_key = this.clientSessionAuthKey;
        params.key = apiKey !== null ? apiKey : this.apiKey;
        params.wait_for_result = !progressCallback;

        const response = await this.fetchAsync(url, params, true);

        if (response.success) {
            const jobID = response.job_id;
            const progressInfo = {
                job_id: jobID,
                progress: 0,
                queue_position: -1,
                estimate: -1
            };
            if (progressCallback){
                progressCallback(progressInfo, null); // Initial progress update

                if (progressStream) {
                    this.setupProgressStream(jobID, resultCallback, progressCallback);
                } else {
                    this.pollProgress(url, jobID, resultCallback, progressCallback);
                }
            }
            else {
                resultCallback(response);
            }
        }
        else {
            resultCallback(response);
        }
    }

    /**
     * Method to set up a progress stream.
     * @async
     * @param {string} jobID - The ID of the job for the progress stream.
     * @param {function} progressCallback - The callback for progress updates.
     */
    async setupProgressStream(jobID, resultCallback, progressCallback) {
        console.error('Error: The stream feature is not yet fully implemented. Please call doAPIRequest() without progressStream=true.');
        alert('Attention: The stream feature is not yet fully implemented. Please call doAPIRequest() without progressStream=true.')
        const eventSourceURL = `${this.apiServerUrl}stream_progress?client_session_auth_key=${encodeURIComponent(this.clientSessionAuthKey)}&job_id=${encodeURIComponent(jobID)}`;
        const eventSource = new EventSource(eventSourceURL);

        eventSource.onmessage = (event) => {
            const data = JSON.parse(event.data);
            console.log('eventSource.onmessage data:', data);
            const progressInfo = {
                progress: data.progress,
                queue_position: data.queue_position,
                estimate: -1
            };

            progressCallback(progressInfo, data.progress_data);

            if (data.job_state === 'done') {
                const jobResult = data.job_result;
                jobResult.job_id = data.job_id;
                jobResult.success = data.success;
                resultCallback(jobResult);
                eventSource.close();
            } else if (data.job_state === 'canceled') {
                eventSource.close();
            }
        };
    }

    /**
     * Method for polling progress updates.
     * @async
     * @param {string} url - The URL for progress updates.
     * @param {string} jobID - The ID of the job for progress updates.
     * @param {function} resultCallback - The callback after the request is completed.
     * @param {function} progressCallback - The callback for progress updates.
     */
    async pollProgress(url, jobID, resultCallback, progressCallback) {

        const progressURL = `${this.apiServerUrl}progress/${this.endpointName}`;
        const fetchProgress = async (progressURL, params) => {
            try {
                return await this.fetchAsync(progressURL, params, true);
            } catch (error) {
                console.error('Error fetching progress data:', error);
                return {};
            }
        }

        const checkProgress = async () => {
            let params = new Object();
            if ((jobID in this.progressInputParams) || (null in this.progressInputParams)) {
                Object.assign(params, this.progressInputParams[jobID]);
                delete this.progressInputParams[jobID];
                delete this.progressInputParams[null];
            }
            params.job_id = jobID;
            params.key = this.apiKey;
            params.canceled = (jobID in this.jobsCanceled) || (null in this.jobsCanceled) ? true : false;
            delete this.jobsCanceled[jobID];
            delete this.jobsCanceled[null];

            
            const result = await fetchProgress(progressURL, params);
            if (result.success) {
                if (result.job_state === 'done' && result.progress === undefined) {
                    const jobResult = result.job_result;
                    resultCallback(jobResult);
                } else {
                    const progress = result.progress;
                    const progressInfo = {
                        progress: progress.progress,
                        queue_position: progress.queue_position,
                        estimate: progress.estimate
                    };
                    progressCallback(progressInfo, progress.progress_data);
                    if (result.job_state === 'canceled') {
                        // Do nothing
                    } else {
                        setTimeout(checkProgress.bind(this), this.defaultProgressIntervall);        
                    }
                }
            }
        }
        checkProgress(this.defaultProgressIntervall);
    }


    async cancelRequest(jobID = null) {
        this.jobsCanceled[jobID] = true;
    }

    async append_progressInputParams(jobID, params) {
        if (jobID in this.progressInputParams) {
            this.progressInputParams[jobID].push(params)
           
        }
        else {
            this.progressInputParams[jobID] = [ params, ]
        }
    }


    async stringifyObjects(params) {
        var transformedParams = new Object()
        for (let key in params) {
            if (params.hasOwnProperty(key)) {
                if (typeof params[key] === 'object' && params[key] !== null) {
                    transformedParams[key] = JSON.stringify(params[key]);
                } else {
                    transformedParams[key] = params[key];
                }
            }
        }
        return transformedParams;
    }
}


/**
 * Simple single call interface for API requests.
 * @function
 * @param {string} endpointName - The name of the API endpoint.
 * @param {Object} params - The parameters of the API request.
 * @param {function} resultCallback - The callback after the request is completed.
 * @param {function} [progressCallback=null] - The callback for progress updates.
 */
function doAPIRequest(endpointName, params, resultCallback, apiKey = null, progressCallback = null) {
    const model = new Aki(endpointName, apiKey);
    model.initAPIKey((data) => {
        model.doAPIRequest(params, resultCallback, progressCallback);
    });
}

// Export the Aki class and doAPIRequest function for Node.js only to avoid the console error in browsers
if (typeof module !== "undefined") {
    module.exports = { Aki, doAPIRequest };
}

