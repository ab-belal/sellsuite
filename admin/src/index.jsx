/**
 * SellSuite Admin Settings App
 * 
 * Main entry point for the React-based admin settings interface
 */

import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App.jsx';

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', () => {
    const rootElement = document.getElementById('sellsuite-settings-root');
    
    if (rootElement) {
        const root = createRoot(rootElement);
        root.render(<App />);
    }
});
