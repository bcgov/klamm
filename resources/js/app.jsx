import './bootstrap';
import React from 'react';
import ReactDom, { createRoot } from 'react-dom/client';
import RenderedFormView from './Pages/RenderedForms/View'
import "@carbon/styles/css/styles.css";

const rootElement = document.getElementById('root');

if (rootElement) {
    const root = createRoot(rootElement);
    root.render(<RenderedFormView />);
} else {
    console.log('Root not found!')
}