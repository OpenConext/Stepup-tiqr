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
    console.log(this.correlationLoggingId);
    return jQuery.get(this.apiUrl + '?correlation-id=' + this.correlationLoggingId, callback).fail(errorCallback);
  }
}
