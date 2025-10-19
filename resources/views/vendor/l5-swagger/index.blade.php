<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self';">
    <title>{{ e(config('l5-swagger.documentations.'.$documentation.'.api.title', 'API Documentation')) }}</title>
    <link rel="preload" href="{{ l5_swagger_asset($documentation, 'swagger-ui.css') }}" as="style">
    <link rel="preload" href="{{ l5_swagger_asset($documentation, 'swagger-ui-bundle.js') }}" as="script">
    <link rel="preload" href="{{ l5_swagger_asset($documentation, 'swagger-ui-standalone-preset.js') }}" as="script">
    <link rel="stylesheet" type="text/css" href="{{ l5_swagger_asset($documentation, 'swagger-ui.css') }}">
    <link rel="icon" type="image/png" href="{{ l5_swagger_asset($documentation, 'favicon-32x32.png') }}" sizes="32x32"/>
    <link rel="icon" type="image/png" href="{{ l5_swagger_asset($documentation, 'favicon-16x16.png') }}" sizes="16x16"/>
    <style>
        html {
            box-sizing: border-box;
            overflow: -moz-scrollbars-vertical;
            overflow-y: scroll;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }
        
        *,
        *:before,
        *:after {
            box-sizing: inherit;
        }

        body {
            margin: 0;
            background: #fafafa;
            font-size: 14px;
            line-height: 1.4;
        }
        
        /* Loading state */
        .swagger-ui-loading {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            font-size: 16px;
            color: #666;
        }
        
        /* Error state */
        .swagger-ui-error {
            padding: 20px;
            margin: 20px;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            color: #721c24;
            text-align: center;
        }
        
        .swagger-ui-error button {
            margin-top: 10px;
            padding: 8px 16px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .swagger-ui-error button:hover {
            background: #c82333;
        }
        
        .swagger-ui-error button:focus {
            outline: 2px solid #007bff;
            outline-offset: 2px;
        }
    </style>
    @if(config('l5-swagger.defaults.ui.display.dark_mode'))
        <style>
            body#dark-mode,
            #dark-mode .scheme-container {
                background: #1b1b1b;
            }
            #dark-mode .scheme-container,
            #dark-mode .opblock .opblock-section-header{
                box-shadow: 0 1px 2px 0 rgba(255, 255, 255, 0.15);
            }
            #dark-mode .operation-filter-input,
            #dark-mode .dialog-ux .modal-ux,
            #dark-mode input[type=email],
            #dark-mode input[type=file],
            #dark-mode input[type=password],
            #dark-mode input[type=search],
            #dark-mode input[type=text],
            #dark-mode textarea{
                background: #343434;
                color: #e7e7e7;
            }
            #dark-mode .title,
            #dark-mode li,
            #dark-mode p,
            #dark-mode table,
            #dark-mode label,
            #dark-mode .opblock-tag,
            #dark-mode .opblock .opblock-summary-operation-id,
            #dark-mode .opblock .opblock-summary-path,
            #dark-mode .opblock .opblock-summary-path__deprecated,
            #dark-mode h1,
            #dark-mode h2,
            #dark-mode h3,
            #dark-mode h4,
            #dark-mode h5,
            #dark-mode .btn,
            #dark-mode .tab li,
            #dark-mode .parameter__name,
            #dark-mode .parameter__type,
            #dark-mode .prop-format,
            #dark-mode .loading-container .loading:after{
                color: #e7e7e7;
            }
            #dark-mode .opblock-description-wrapper p,
            #dark-mode .opblock-external-docs-wrapper p,
            #dark-mode .opblock-title_normal p,
            #dark-mode .response-col_status,
            #dark-mode table thead tr td,
            #dark-mode table thead tr th,
            #dark-mode .response-col_links,
            #dark-mode .swagger-ui{
                color: wheat;
            }
            #dark-mode .parameter__extension,
            #dark-mode .parameter__in,
            #dark-mode .model-title{
                color: #949494;
            }
            #dark-mode table thead tr td,
            #dark-mode table thead tr th{
                border-color: rgba(120,120,120,.2);
            }
            #dark-mode .opblock .opblock-section-header{
                background: transparent;
            }
            #dark-mode .opblock.opblock-post{
                background: rgba(73,204,144,.25);
            }
            #dark-mode .opblock.opblock-get{
                background: rgba(97,175,254,.25);
            }
            #dark-mode .opblock.opblock-put{
                background: rgba(252,161,48,.25);
            }
            #dark-mode .opblock.opblock-delete{
                background: rgba(249,62,62,.25);
            }
            #dark-mode .loading-container .loading:before{
                border-color: rgba(255,255,255,10%);
                border-top-color: rgba(255,255,255,.6);
            }
            #dark-mode svg:not(:root){
                fill: #e7e7e7;
            }
            #dark-mode .opblock-summary-description {
                color: #fafafa;
            }
        </style>
    @endif
</head>

<body @if(config('l5-swagger.defaults.ui.display.dark_mode')) id="dark-mode" @endif>
    <div id="swagger-ui" role="main" aria-label="API Documentation">
        <div class="swagger-ui-loading" role="status" aria-live="polite">
            Loading API Documentation...
        </div>
    </div>
    
    <noscript>
        <div class="swagger-ui-error" role="alert">
            <h2>JavaScript Required</h2>
            <p>This API documentation requires JavaScript to be enabled in your browser.</p>
            <p>Please enable JavaScript and refresh the page to view the documentation.</p>
        </div>
    </noscript>

    <script src="{{ l5_swagger_asset($documentation, 'swagger-ui-bundle.js') }}" defer></script>
    <script src="{{ l5_swagger_asset($documentation, 'swagger-ui-standalone-preset.js') }}" defer></script>
    <script>
        // Configuration object for security
        @php
            $swaggerConfigArray = [
                'apiUrl' => str_replace('?api-docs.json', '', $urlToDocs ?? ''),
                'operationsSorter' => $operationsSorter ?? null,
                'configUrl' => $configUrl ?? null,
                'validatorUrl' => $validatorUrl ?? null,
                'oauth2RedirectUrl' => route('l5-swagger.'.$documentation.'.oauth2_callback', [], $useAbsolutePath ?? false),
                'csrfToken' => csrf_token(),
                'docExpansion' => config('l5-swagger.defaults.ui.display.doc_expansion', 'none'),
                'filter' => (bool)config('l5-swagger.defaults.ui.display.filter', false),
                'persistAuthorization' => (bool)config('l5-swagger.defaults.ui.authorization.persist_authorization', false),
                'oauth2Config' => [
                    'usePkceWithAuthorizationCodeGrant' => (bool)config('l5-swagger.defaults.ui.authorization.oauth2.use_pkce_with_authorization_code_grant', false)
                ],
                'hasOAuth2' => in_array('oauth2', array_column(config('l5-swagger.defaults.securityDefinitions.securitySchemes', []), 'type'))
            ];
        @endphp
        const swaggerConfig = @json($swaggerConfigArray);

        // Global error handler
        window.addEventListener('error', function(event) {
            console.error('Global error:', event.error);
            showError('An unexpected error occurred. Please refresh the page.');
        });

        window.addEventListener('unhandledrejection', function(event) {
            console.error('Unhandled promise rejection:', event.reason);
            showError('An unexpected error occurred. Please refresh the page.');
        });

        // Initialize Swagger UI when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            // Validate required dependencies
            if (typeof SwaggerUIBundle === 'undefined') {
                showError('Swagger UI dependencies failed to load. Please refresh the page.');
                return;
            }
            
            initializeSwaggerUI();
        });

        function initializeSwaggerUI() {
            try {
                // Clear loading message
                const swaggerContainer = document.getElementById('swagger-ui');
                if (swaggerContainer) {
                    swaggerContainer.innerHTML = '';
                }

                // Build Swagger UI
                const ui = SwaggerUIBundle({
                    dom_id: '#swagger-ui',
                    url: swaggerConfig.apiUrl,
                    operationsSorter: swaggerConfig.operationsSorter,
                    configUrl: swaggerConfig.configUrl,
                    validatorUrl: swaggerConfig.validatorUrl,
                    oauth2RedirectUrl: swaggerConfig.oauth2RedirectUrl,

                    requestInterceptor: function(request) {
                        // Add CSRF token to requests
                        if (swaggerConfig.csrfToken) {
                            request.headers['X-CSRF-TOKEN'] = swaggerConfig.csrfToken;
                        }
                        
                        // Add security headers
                        request.headers['X-Requested-With'] = 'XMLHttpRequest';
                        
                        return request;
                    },

                    responseInterceptor: function(response) {
                        // Log errors for debugging
                        if (response.status >= 400) {
                            console.warn('API Error:', response.status, response.statusText);
                        }
                        return response;
                    },

                    presets: [
                        SwaggerUIBundle.presets.apis,
                        SwaggerUIStandalonePreset
                    ],

                    plugins: [
                        SwaggerUIBundle.plugins.DownloadUrl
                    ],

                    layout: "StandaloneLayout",
                    docExpansion: swaggerConfig.docExpansion,
                    deepLinking: true,
                    filter: swaggerConfig.filter,
                    persistAuthorization: swaggerConfig.persistAuthorization,
                    tryItOutEnabled: true,
                    supportedSubmitMethods: ['get', 'post', 'put', 'delete', 'patch', 'head', 'options'],

                    onComplete: function() {
                        console.log('Swagger UI loaded successfully');
                    },

                    onFailure: function(error) {
                        console.error('Failed to load Swagger UI:', error);
                        showError('Failed to load API documentation. Please refresh the page.');
                    }
                });

                // Store UI instance globally for debugging
                window.ui = ui;

                // Initialize OAuth2 if configured
                if (swaggerConfig.hasOAuth2 && ui.initOAuth) {
                    ui.initOAuth(swaggerConfig.oauth2Config);
                }

            } catch (error) {
                console.error('Error initializing Swagger UI:', error);
                showError('Error initializing API documentation.');
            }
        }

        function showError(message) {
            const swaggerContainer = document.getElementById('swagger-ui');
            if (swaggerContainer) {
                // Create error element safely
                const errorDiv = document.createElement('div');
                errorDiv.className = 'swagger-ui-error';
                errorDiv.setAttribute('role', 'alert');
                
                const title = document.createElement('h2');
                title.textContent = 'Error';
                
                const messageP = document.createElement('p');
                messageP.textContent = message || 'An unknown error occurred.';
                
                const reloadBtn = document.createElement('button');
                reloadBtn.textContent = 'Reload Page';
                reloadBtn.setAttribute('type', 'button');
                reloadBtn.addEventListener('click', function() {
                    location.reload();
                });
                
                errorDiv.appendChild(title);
                errorDiv.appendChild(messageP);
                errorDiv.appendChild(reloadBtn);
                
                swaggerContainer.innerHTML = '';
                swaggerContainer.appendChild(errorDiv);
            }
        }

        function escapeHtml(text) {
            if (typeof text !== 'string') {
                return '';
            }
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
