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
    const url = this.apiUrl + (this.apiUrl.includes('?') ? '&' : '?') + 'correlation-id=' + this.correlationLoggingId;

    return jQuery.get(url, callback).fail(errorCallback);
  }
}
