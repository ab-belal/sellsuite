const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

module.exports = (env, argv) => {
    const isDevelopment = argv.mode === 'development';

    return {
        mode: argv.mode || 'development',
        entry: {
            app: './src/index.jsx',
            admin: './assets/scss/style.scss'
        },
        output: {
            path: path.resolve(__dirname, 'build'),
            filename: '[name].js',
        },
        module: {
            rules: [
                {
                    test: /\.(js|jsx)$/,
                    exclude: /node_modules/,
                    use: {
                        loader: 'babel-loader',
                        options: {
                            presets: [
                                '@babel/preset-env',
                                ['@babel/preset-react', { runtime: 'automatic' }]
                            ]
                        }
                    }
                },
                {
                    test: /\.(css|scss)$/,
                    exclude: /assets\/scss\/style\.scss$/,
                    use: [
                        'style-loader',
                        'css-loader',
                        'sass-loader'
                    ]
                },
                {
                    test: /assets\/scss\/style\.scss$/,
                    use: [
                        MiniCssExtractPlugin.loader,
                        'css-loader',
                        'sass-loader'
                    ]
                }
            ]
        },
        plugins: [
            new MiniCssExtractPlugin({
                filename: '../assets/css/sellsuite-admin.css'
            })
        ],
        resolve: {
            extensions: ['.js', '.jsx']
        },
        externals: {
            react: 'React',
            'react-dom': 'ReactDOM',
            'react-dom/client': 'ReactDOM'
        }
    };
};
