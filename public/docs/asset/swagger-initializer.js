window.onload = function() {
  //<editor-fold desc="Changeable Configuration Block">

  // Updated configuration for Laravel API documentation
  window.ui = SwaggerUIBundle({
    url: "/api-docs.json",
    dom_id: '#swagger-ui',
    deepLinking: true,
    presets: [
      SwaggerUIBundle.presets.apis,
      SwaggerUIStandalonePreset
    ],
    plugins: [
      SwaggerUIBundle.plugins.DownloadUrl
    ],
    layout: "StandaloneLayout",
    onComplete: function() {
      console.log("Swagger UI loaded successfully with API definition from /api-docs.json");
    },
    onFailure: function(data) {
      console.error("Failed to load Swagger UI:", data);
    }
  });

  //</editor-fold>
};
