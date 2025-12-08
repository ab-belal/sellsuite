/**
 * Point Expiry Component
 * 
 * Configure point expiration settings
 */

import React from 'react';

const PointExpiry = ({ settings, handleChange }) => {
    return (
        <div className="point-management-tab-panel">
            <h3>Point Expiry</h3>

            <div className="point-management-field">
                <label className="point-management-toggle">
                    <input
                        type="checkbox"
                        checked={settings.enable_expiry}
                        onChange={(e) => handleChange('enable_expiry', e.target.checked)}
                        disabled={!settings.points_enabled}
                    />
                    <span className="point-management-toggle-label">Enable Point Expiration</span>
                </label>
                <p className="point-management-field-description">
                    Points will expire after a certain period
                </p>
            </div>

            {settings.enable_expiry && (
                <div className="point-management-field">
                    <label className="point-management-label">
                        Expiry Days
                        <input
                            type="number"
                            min="1"
                            step="1"
                            value={settings.expiry_days}
                            onChange={(e) => handleChange('expiry_days', parseInt(e.target.value))}
                            disabled={!settings.points_enabled}
                        />
                    </label>
                    <p className="point-management-field-description">
                        Number of days until points expire after being earned
                    </p>
                </div>
            )}
        </div>
    );
};

export default PointExpiry;