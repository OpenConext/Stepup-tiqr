import { StatusPollService } from './StatusPollService';
import { StatusClient } from './Client/StatusClient';
import { AuthenticationPageService } from './AuthenticationPageService';
import { NotificationClient } from './Client/NotificationClient';
import { SlideableComponent } from './Component/SlideableComponent';
import { HideableComponent } from './Component/HideableComponent';
import { MobileOnlyComponent } from "./Component/MobileOnlyComponent";
import jQuery from 'jquery';

declare global {
  interface Window {
    bootstrapAuthentication: (statusApiUrl: string, notificationApiUrl: string, correlationLoggingId: string) => AuthenticationPageService;
  }
}

window.bootstrapAuthentication = (statusApiUrl: string, notificationApiUrl: string, correlationLoggingId: string) => {
  const statusClient = new StatusClient(statusApiUrl, correlationLoggingId);
  const notificationClient = new NotificationClient(notificationApiUrl);
  const pollingService = new StatusPollService(statusClient);

  const spinnerComponent = new SlideableComponent(jQuery('.spinner-container'));
  const qrComponent = new SlideableComponent(jQuery('#qr'));
  const otpFormComponent = new HideableComponent(jQuery('#otpform'));
  const challengeExpiredComponent = new HideableComponent(jQuery('#challengeExpired'));
  const statusErrorComponent = new HideableComponent(jQuery('#status-request-error'));
  const notificationErrorComponent = new HideableComponent(jQuery('#notificationError'));
  new MobileOnlyComponent(jQuery('#open-in-app'));

  const authenticationPageService = new AuthenticationPageService(
    pollingService,
    notificationClient,
    spinnerComponent,
    qrComponent,
    otpFormComponent,
    challengeExpiredComponent,
    statusErrorComponent,
    notificationErrorComponent,
  );

  authenticationPageService.switchToPolling();

  return authenticationPageService;
};

// Fallback for non-SVG supporting browsers (for the spinner)
if (typeof SVGRect === 'undefined') {
  jQuery('img.spinner').attr('src', '/build/images/spinner.gif').attr('height', '38');
}
