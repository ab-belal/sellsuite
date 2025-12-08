/**
 * Earning Points Component
 * 
 * Configure how customers earn points
 */

import React from 'react';

const EarningPoints = ({ settings, handleChange }) => {
    return (
        <div className="point-management-tab-panel">
            <h3>Earning Points</h3>

            <div className="point-management-field">
                <label className="point-management-label">
                    Calculation Method
                    <select
                        value={settings.point_calculation_method}
                        onChange={(e) => handleChange('point_calculation_method', e.target.value)}
                        disabled={!settings.points_enabled}
                    >
                        <option value="fixed">Fixed Points per Dollar</option>
                        <option value="percentage">Percentage of Price</option>
                    </select>
                </label>
            </div>

            {settings.point_calculation_method === 'fixed' && (
                <div className="point-management-field">
                    <label className="point-management-label">
                        Points per Dollar Spent
                        <input
                            type="number"
                            min="0"
                            step="1"
                            value={settings.points_per_dollar}
                            onChange={(e) => handleChange('points_per_dollar', parseInt(e.target.value))}
                            disabled={!settings.points_enabled}
                        />
                    </label>
                    <p className="point-management-field-description">
                        Number of points earned for every dollar spent
                    </p>
                </div>
            )}

            {settings.point_calculation_method === 'percentage' && (
                <div className="point-management-field">
                    <label className="point-management-label">
                        Points as Percentage of Price (%)
                        <input
                            type="number"
                            min="0"
                            max="100"
                            step="0.1"
                            value={settings.points_percentage}
                            onChange={(e) => handleChange('points_percentage', parseFloat(e.target.value))}
                            disabled={!settings.points_enabled}
                        />
                    </label>
                    <p className="point-management-field-description">
                        Points earned as a percentage of product price
                    </p>
                </div>
            )}
        </div>
    );
};

export default EarningPoints;