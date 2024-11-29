import jQuery from 'jquery';

export interface PendingRequest {
  abort: () => void;
}

export class StatusClient {
  constructor(
    private apiUrl: string,
    private correlationLoggingId: string,
  ) {
  }

  /**
   * Request status form the API.
   */
  public request(callback: (status: string) => void, errorCallback: (error: unknown) => void): PendingRequest {
    let url;
    if(this.correlationLoggingId !== ''){
      url = this.apiUrl + (this.apiUrl.includes('?') ? '&' : '?') + 'correlation-id=' + this.correlationLoggingId;
    }else{
      url = this.apiUrl;
    }

    return jQuery.get(url, callback).fail(errorCallback);
  }
}
