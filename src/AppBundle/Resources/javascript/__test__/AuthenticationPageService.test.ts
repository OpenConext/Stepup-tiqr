import 'jest';
import { AuthenticationPageService } from '../AuthenticationPageService';

describe('AuthenticationPageService', () => {

  function createTestContext() {
    const notificationClient = {
      send: jest.fn(),
    };
    const pollingService =  {
      waitAndRequestStatus: jest.fn(),
      stop: jest.fn(),
    };
    function createMockComponent() {
      return {
        show: jest.fn(),
        hide: jest.fn(),
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

  let context = createTestContext();

  beforeEach(() => {
    context = createTestContext();
  });

  it('When switching to manual, the qr code should be shown', () => {
    context.authenticationPageService.switchToManual();
    expect(context.spinnerComponent.hide).toBeCalled();
    expect(context.spinnerComponent.show).not.toBeCalled();
    expect(context.qrComponent.show).toBeCalled();
  });

  describe('When notification failed', () => {
    beforeEach(() => {
      context = createTestContext();
      context.authenticationPageService.switchToOtp();
    });

    it('The qr code should be shown', () => {
      expect(context.spinnerComponent.hide).toBeCalled();
      expect(context.spinnerComponent.show).not.toBeCalled();
      expect(context.qrComponent.show).toBeCalled();
      expect(context.otpFormComponent.show).toBeCalled();
    });

    it('Polling should not be disabled', () => {
      expect(context.pollingService.stop).not.toBeCalled();
    });
  });

  describe('When challenge has expired', () => {
    beforeEach(() => {
      context = createTestContext();
      context.authenticationPageService.switchToChallengeHasExpired();
    });

    it('The warning should be shown', () => {
      expect(context.challengeExpiredComponent.show).toBeCalled();
    });

    it('Polling should be disabled', () => {
      expect(context.pollingService.stop).toBeCalled();
    });
  });

  describe('When notification failed', () => {

    beforeEach(() => {
      context = createTestContext();
      context.authenticationPageService.switchToNotificationFailed();
    });

    it('The warning and qr code should be shown', () => {
      expect(context.notificationErrorComponent.show).toBeCalled();
      expect(context.qrComponent.show).toBeCalled();
    });

    it('Polling should not be disabled', () => {
      expect(context.pollingService.stop).not.toBeCalled();
    });
  });
});
