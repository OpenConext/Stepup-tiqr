/**
 * @jest-environment jsdom
 */
import 'jest';
import { AuthenticationPageService } from '../AuthenticationPageService';

describe('AuthenticationPageService', () => {
  let context = createTestContext();

  beforeEach(() => {
    context = createTestContext();
  });

  describe('When switching to manual', () => {
    beforeEach(() => {
      context.authenticationPageService.switchToManual();
    });

    it('The qr code should be shown', () => {
      expect(context.qrComponent.isVisible()).toBeTruthy();
    });

    it('Polling should not be disabled', () => {
      expect(context.pollingService.stop).not.toBeCalled();
    });

    it('The spinner should be hidden', () => {
      expect(context.spinnerComponent.isVisible()).toBeFalsy();
    });
  });

  describe('When notification failed', () => {
    beforeEach(() => {
      context.authenticationPageService.switchToOtp();
    });

    it('The qr code should be shown', () => {
      expect(context.qrComponent.isVisible()).toBeTruthy();
    });

    it('Polling should not be disabled', () => {
      expect(context.pollingService.stop).not.toBeCalled();
    });

    it('The spinner should be hidden', () => {
      expect(context.spinnerComponent.isVisible()).toBeFalsy();
    });
  });

  describe('When challenge has expired', () => {
    beforeEach(() => {
      context.authenticationPageService.switchToChallengeHasExpired();
    });

    it('The warning should be shown', () => {
      expect(context.challengeExpiredComponent.isVisible()).toBeTruthy();
    });

    it('The spinner should be hidden', () => {
      expect(context.spinnerComponent.isVisible()).toBeFalsy();
    });

    it('The qr code should not be shown', () => {
      expect(context.qrComponent.isVisible()).toBeFalsy();
    });

    it('Polling should be disabled', () => {
      expect(context.pollingService.stop).toBeCalled();
    });
  });

  describe('When notification failed', () => {
    beforeEach(() => {
      context.authenticationPageService.switchToNotificationFailed();
    });
    it('The warning and qr code should be shown', () => {
      expect(context.notificationErrorComponent.isVisible()).toBeTruthy();
      expect(context.qrComponent.isVisible()).toBeTruthy();
    });
    it('The spinner should be hidden', () => {
      expect(context.spinnerComponent.isVisible()).toBeFalsy();
    });
    it('Polling should not be disabled', () => {
      expect(context.pollingService.stop).not.toBeCalled();
    });
  });

  describe('When no push device registered', () => {
    beforeEach(() => {
      context.authenticationPageService.switchToNoDevice();
    });
    it('The spinner should be hidden', () => {
      expect(context.spinnerComponent.isVisible()).toBeFalsy();
    });
    it('The warning should not be shown', () => {

      expect(context.notificationErrorComponent.isVisible()).toBeFalsy();
    });
    it('Polling should not be disabled', () => {
      expect(context.pollingService.stop).not.toBeCalled();
    });
  });

  describe('When starting with polling', () => {
    beforeEach(() => {
      context.authenticationPageService.switchToPolling();
    });
    it('The spinner should be hidden', () => {
      expect(context.spinnerComponent.isVisible()).toBeTruthy();
    });
    it('Polling should be enabled', () => {
      expect(context.pollingService.waitAndRequestStatus).toBeCalled();
      expect(context.pollingService.stop).not.toBeCalled();
    });
  });

  describe('When status request failed', () => {
    beforeEach(() => {
      context.authenticationPageService.switchToStatusRequestError();
    });
    it('The spinner and QR code should be hidden', () => {
      expect(context.spinnerComponent.isVisible()).toBeFalsy();
      expect(context.qrComponent.isVisible()).toBeFalsy();
    });
    it('The warning should be shown', () => {
      expect(context.statusErrorComponent.isVisible()).toBeTruthy();
    });
    it('Polling should be disabled', () => {
      expect(context.pollingService.stop).toBeCalled();
    });
  });

  describe('When status is handled', () => {
    let successCallback: ((status: string) => void) | undefined;
    let errorCallback: ((error: unknown) => void) | undefined;

    beforeEach(() => {
      context.pollingService.waitAndRequestStatus = ((success: any, error: any) => {
        successCallback = success;
        errorCallback = error;
      }) as any;
      context.authenticationPageService.switchToPolling();
    });
    it('Should start new polling when status is pending', () => {
      if (!successCallback || !errorCallback) {
        throw new Error('Should have started status request');
      }
      context.pollingService.waitAndRequestStatus = jest.fn();
      successCallback('pending');
      expect(context.pollingService.waitAndRequestStatus).toBeCalled();
    });
    it('Should handle challenge expired', () => {
      if (!successCallback || !errorCallback) {
        throw new Error('Should have started status request');
      }
      const spy = jest.spyOn(context.authenticationPageService, 'switchToChallengeHasExpired');
      successCallback('challenge-expired');
      expect(spy).toBeCalled();
    });
    it('Should handle authn error (invalid request)', () => {
      if (!successCallback || !errorCallback) {
        throw new Error('Should have started status request');
      }
      const spy = jest.spyOn(context.authenticationPageService, 'switchToStatusRequestError');
      successCallback('invalid-request');
      expect(spy).toBeCalled();
    });

    it('Should handle challenge expired', () => {
      if (!successCallback || !errorCallback) {
        throw new Error('Should have started status request');
      }
      const spy = jest.spyOn(context.authenticationPageService, 'switchToChallengeHasExpired');
      successCallback('challenge-expired');
      expect(spy).toBeCalled();
    });

    it('Handles needs-refresh', () => {
      if (!successCallback || !errorCallback) {
        throw new Error('Should have started status request');
      }
      const spy = context.authenticationPageService.reloadPage = jest.fn();
      successCallback('needs-refresh');
      expect(spy).toBeCalled();
    });

    it('Handles connection errors', () => {
      if (!successCallback || !errorCallback) {
        throw new Error('Should have started status request');
      }
      const spy = jest.spyOn(context.authenticationPageService, 'switchToStatusRequestError');
      errorCallback('Random error');
      expect(spy).toBeCalled();
    });
  });

  describe('When push notification response is handled', () => {
    let successCallback: ((status: string) => void) | undefined;
    let errorCallback: ((error: unknown) => void) | undefined;

    beforeEach(() => {
      context.notificationClient.send = ((success: any, error: any) => {
        successCallback = success;
        errorCallback = error;
      }) as any;
      context.authenticationPageService.switchToPolling();
    });
    it('Should do nothing when push notification succeeded', () => {
      if (!successCallback || !errorCallback) {
        throw new Error('Should have started notification request');
      }
      successCallback('success');
      // Should change nothing.
    });

    it('Should show push notification when failed', () => {
      if (!successCallback || !errorCallback) {
        throw new Error('Should have started notification request');
      }
      const spy = jest.spyOn(context.authenticationPageService, 'switchToNotificationFailed');
      successCallback('error');
      expect(spy).toBeCalled();
    });

    it('Should show qr when there is no device registered', () => {
      if (!successCallback || !errorCallback) {
        throw new Error('Should have started notification request');
      }
      const spy = jest.spyOn(context.authenticationPageService, 'switchToNoDevice');
      successCallback('no-device');
      expect(spy).toBeCalled();
    });

    it('Should handle connection errors', () => {
      if (!successCallback || !errorCallback) {
        throw new Error('Should have started notification request');
      }
      const spy = jest.spyOn(context.authenticationPageService, 'switchToNotificationFailed');
      errorCallback('Some error');
      expect(spy).toBeCalled();
    });
  });

  function createTestContext() {
    const notificationClient = {
      send: jest.fn(),
    };
    const pollingService = {
      waitAndRequestStatus: jest.fn(),
      stop: jest.fn(),
    };

    function createMockComponent() {
      let visible = false;
      return {
        isVisible: () => visible,
        show: () => {
          visible = true;
        },
        hide: () => {
          visible = false;
        },
      };
    }

    const spinnerComponent = createMockComponent();
    const qrComponent = createMockComponent();
    const otpFormComponent = createMockComponent();
    const challengeExpiredComponent = createMockComponent();
    const statusErrorComponent = createMockComponent();
    const notificationErrorComponent = createMockComponent();

    const authenticationPageService = new AuthenticationPageService(
      pollingService as any,
      notificationClient as any,
      spinnerComponent,
      qrComponent,
      otpFormComponent,
      challengeExpiredComponent,
      statusErrorComponent,
      notificationErrorComponent,
    );
    return {
      pollingService,
      authenticationPageService,
      notificationClient,
      spinnerComponent,
      qrComponent,
      otpFormComponent,
      challengeExpiredComponent,
      statusErrorComponent,
      notificationErrorComponent,
    };
  }
});
