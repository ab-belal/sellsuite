/**
 * General Settings Component
 * 
 * General point management settings
 */

import React from 'react';

const General = ({ settings, handleChange }) => {
    return (
        <div className="point-management-tab-panel">
            <h3>General Settings</h3>
            
            <div className="point-management-field">
                <label className="point-management-toggle">
                    <input
                        type="checkbox"
                        checked={settings.points_enabled}
                        onChange={(e) => handleChange('points_enabled', e.target.checked)}
                    />
                    <span className="point-management-toggle-label">Enable Points System</span>
                </label>
                <p className="point-management-field-description">
                    Allow customers to earn and redeem points. This must be enabled to configure other settings.
                </p>
            </div>

            <div className="point-management-field">
                <label className="point-management-label">
                    Conversion Rate
                    <input
                        type="number"
                        min="0.01"
                        step="0.01"
                        value={settings.conversion_rate}
                        onChange={(e) => handleChange('conversion_rate', parseFloat(e.target.value))}
                        disabled={!settings.points_enabled}
                    />
                </label>
                <p className="point-management-field-description">
                    How much 1 point is worth in your store currency (e.g., 1 point = $1)
                </p>
            </div>
        </div>
    );
};

export default General;