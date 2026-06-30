import { Application } from '@hotwired/stimulus';

const app = Application.start();
import SupportSearchController from './support_search_controller.ts';
app.register('support-search', SupportSearchController);
// console.log removed in production
