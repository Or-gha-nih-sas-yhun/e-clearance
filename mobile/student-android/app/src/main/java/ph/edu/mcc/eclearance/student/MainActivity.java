package ph.edu.mcc.eclearance.student;

import android.Manifest;
import android.annotation.SuppressLint;
import android.app.Activity;
import android.app.DownloadManager;
import android.content.ActivityNotFoundException;
import android.content.Context;
import android.content.Intent;
import android.content.pm.ApplicationInfo;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.net.ConnectivityManager;
import android.net.Network;
import android.net.NetworkCapabilities;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.os.Environment;
import android.view.Gravity;
import android.view.View;
import android.view.ViewGroup;
import android.window.OnBackInvokedCallback;
import android.window.OnBackInvokedDispatcher;
import android.webkit.CookieManager;
import android.webkit.DownloadListener;
import android.webkit.RenderProcessGoneDetail;
import android.webkit.SslErrorHandler;
import android.webkit.URLUtil;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Button;
import android.widget.FrameLayout;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.ProgressBar;
import android.widget.TextView;
import android.widget.Toast;

import java.util.Locale;

public final class MainActivity extends Activity {
    private static final String SAVED_WEBVIEW_STATE = "student_webview_state";
    private static final int FILE_CHOOSER_REQUEST = 4101;
    private static final int STORAGE_PERMISSION_REQUEST = 4102;

    private WebView webView;
    private ProgressBar pageProgress;
    private LinearLayout errorPanel;
    private LinearLayout splashPanel;
    private boolean splashDismissed;
    private ValueCallback<Uri[]> fileChooserCallback;
    private PendingDownload pendingDownload;
    private OnBackInvokedCallback backInvokedCallback;
    private String serverRoot;
    private boolean mainFrameFailed;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        getWindow().setStatusBarColor(getColor(R.color.mcc_navy));
        getWindow().setNavigationBarColor(getColor(R.color.mcc_surface));
        getWindow().getDecorView().setSystemUiVisibility(View.SYSTEM_UI_FLAG_LIGHT_NAVIGATION_BAR);

        // Always use the official HTTPS service. Older versions allowed a
        // local address to be saved; ignoring it prevents launches from being
        // redirected out of the WebView and into the browser.
        serverRoot = getString(R.string.default_server_root);
        setContentView(createScreen());
        configureWebView();
        registerPredictiveBackCallback();

        Bundle webState = savedInstanceState == null ? null : savedInstanceState.getBundle(SAVED_WEBVIEW_STATE);
        if (webState == null || webView.restoreState(webState) == null) {
            loadStudentPortal();
        }
    }

    private View createScreen() {
        LinearLayout screen = new LinearLayout(this);
        screen.setOrientation(LinearLayout.VERTICAL);
        screen.setBackgroundColor(getColor(R.color.mcc_surface));
        screen.setFitsSystemWindows(true);

        pageProgress = new ProgressBar(this, null, android.R.attr.progressBarStyleHorizontal);
        pageProgress.setMax(100);
        pageProgress.setProgressTintList(android.content.res.ColorStateList.valueOf(getColor(R.color.mcc_blue)));
        screen.addView(pageProgress, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(3)));

        FrameLayout content = new FrameLayout(this);
        screen.addView(content, new LinearLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT,
            0,
            1f
        ));

        webView = new WebView(this);
        webView.setBackgroundColor(getColor(R.color.mcc_surface));
        // The portal implements pull-to-refresh itself, so the WebView's own
        // stretch/glow is turned off rather than fighting the gesture.
        webView.setOverScrollMode(View.OVER_SCROLL_NEVER);
        content.addView(webView, new FrameLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT,
            ViewGroup.LayoutParams.MATCH_PARENT
        ));

        errorPanel = createErrorPanel();
        errorPanel.setVisibility(View.GONE);
        content.addView(errorPanel, new FrameLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT,
            ViewGroup.LayoutParams.MATCH_PARENT
        ));

        // Added last so it sits above the WebView, covering the blank frame
        // between the app opening and the portal's first paint.
        splashPanel = createSplashPanel();
        content.addView(splashPanel, new FrameLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT,
            ViewGroup.LayoutParams.MATCH_PARENT
        ));

        return screen;
    }

    private LinearLayout createSplashPanel() {
        LinearLayout panel = new LinearLayout(this);
        panel.setOrientation(LinearLayout.VERTICAL);
        panel.setGravity(android.view.Gravity.CENTER);
        panel.setBackgroundColor(getColor(R.color.mcc_surface));
        // Swallows taps so nothing behind it can be pressed while it shows.
        panel.setClickable(true);

        ImageView logo = new ImageView(this);
        logo.setImageResource(R.drawable.mcc_eclearance_logo);
        logo.setScaleType(ImageView.ScaleType.FIT_CENTER);
        panel.addView(logo, new LinearLayout.LayoutParams(dp(148), dp(148)));

        TextView name = new TextView(this);
        name.setText(R.string.app_name);
        name.setTextColor(getColor(R.color.mcc_navy));
        name.setTextSize(17f);
        name.setTypeface(name.getTypeface(), android.graphics.Typeface.BOLD);
        name.setGravity(android.view.Gravity.CENTER);
        LinearLayout.LayoutParams nameParams = new LinearLayout.LayoutParams(
            ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT);
        nameParams.topMargin = dp(18);
        panel.addView(name, nameParams);

        ProgressBar spinner = new ProgressBar(this);
        spinner.setIndeterminateTintList(
            android.content.res.ColorStateList.valueOf(getColor(R.color.mcc_blue)));
        LinearLayout.LayoutParams spinnerParams = new LinearLayout.LayoutParams(dp(30), dp(30));
        spinnerParams.topMargin = dp(22);
        panel.addView(spinner, spinnerParams);

        return panel;
    }

    /**
     * Takes the splash away once the portal has something to show. Only the
     * first load is covered; later navigations use the thin progress bar, so
     * the screen is never hidden again mid-session.
     */
    private void dismissSplash() {
        if (splashDismissed || splashPanel == null) {
            return;
        }
        splashDismissed = true;
        splashPanel.animate().alpha(0f).setDuration(260L)
            .withEndAction(() -> splashPanel.setVisibility(View.GONE))
            .start();
    }

    private LinearLayout createErrorPanel() {
        LinearLayout panel = new LinearLayout(this);
        panel.setOrientation(LinearLayout.VERTICAL);
        panel.setGravity(Gravity.CENTER);
        panel.setPadding(dp(30), dp(30), dp(30), dp(30));
        panel.setBackgroundColor(getColor(R.color.mcc_surface));

        ImageView icon = new ImageView(this);
        icon.setImageResource(R.drawable.mcc_eclearance_logo);
        panel.addView(icon, new LinearLayout.LayoutParams(dp(86), dp(86)));

        TextView heading = new TextView(this);
        heading.setText(R.string.connection_error_title);
        heading.setTextColor(getColor(R.color.mcc_navy));
        heading.setTextSize(19);
        heading.setGravity(Gravity.CENTER);
        heading.setTypeface(heading.getTypeface(), android.graphics.Typeface.BOLD);
        LinearLayout.LayoutParams headingParams = new LinearLayout.LayoutParams(
            ViewGroup.LayoutParams.WRAP_CONTENT,
            ViewGroup.LayoutParams.WRAP_CONTENT
        );
        headingParams.topMargin = dp(16);
        panel.addView(heading, headingParams);

        TextView body = new TextView(this);
        body.setText(R.string.connection_error_body);
        body.setTextColor(Color.rgb(100, 120, 140));
        body.setTextSize(13);
        body.setGravity(Gravity.CENTER);
        LinearLayout.LayoutParams bodyParams = new LinearLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT,
            ViewGroup.LayoutParams.WRAP_CONTENT
        );
        bodyParams.topMargin = dp(8);
        panel.addView(body, bodyParams);

        Button retry = new Button(this);
        retry.setText(R.string.retry);
        retry.setTextColor(Color.WHITE);
        retry.setBackgroundTintList(android.content.res.ColorStateList.valueOf(getColor(R.color.mcc_blue)));
        retry.setOnClickListener(view -> loadStudentPortal());
        LinearLayout.LayoutParams retryParams = new LinearLayout.LayoutParams(dp(150), dp(46));
        retryParams.topMargin = dp(18);
        panel.addView(retry, retryParams);

        return panel;
    }

    @SuppressWarnings("SetJavaScriptEnabled")
    private void configureWebView() {
        WebSettings settings = webView.getSettings();
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setAllowFileAccess(false);
        settings.setAllowContentAccess(true);
        settings.setJavaScriptCanOpenWindowsAutomatically(false);
        settings.setSupportMultipleWindows(false);
        settings.setMixedContentMode(WebSettings.MIXED_CONTENT_NEVER_ALLOW);
        settings.setMediaPlaybackRequiresUserGesture(true);
        settings.setBuiltInZoomControls(false);
        settings.setDisplayZoomControls(false);
        settings.setUserAgentString(settings.getUserAgentString() + " MCCStudentAndroid/" + BuildConfig.VERSION_NAME);
        WebView.setWebContentsDebuggingEnabled(isDebuggable());
        settings.setSafeBrowsingEnabled(true);

        CookieManager cookies = CookieManager.getInstance();
        cookies.setAcceptCookie(true);
        cookies.setAcceptThirdPartyCookies(webView, false);

        webView.setWebViewClient(new StudentWebViewClient());
        webView.setWebChromeClient(new StudentWebChromeClient());
        webView.setDownloadListener(new StudentDownloadListener());
    }

    private void loadStudentPortal() {
        hideError();
        if (!hasNetworkConnection()) {
            showError();
            return;
        }
        webView.loadUrl(studentPortalUrl());
    }

    private boolean isAllowedStudentUrl(Uri destination) {
        if (destination == null || "about".equalsIgnoreCase(destination.getScheme())) {
            return true;
        }
        Uri root = Uri.parse(serverRoot);
        if (!samePortalOrigin(root, destination)) {
            return false;
        }

        String rootPath = root.getPath() == null ? "" : root.getPath();
        while (rootPath.endsWith("/")) {
            rootPath = rootPath.substring(0, rootPath.length() - 1);
        }
        String studentPath = rootPath + "/student";
        String privacyPolicyPath = rootPath + "/privacy-policy";
        String destinationPath = destination.getPath() == null ? "" : destination.getPath();
        return destinationPath.equals(studentPath)
            || destinationPath.startsWith(studentPath + "/")
            || destinationPath.equals(privacyPolicyPath)
            || destinationPath.equals(privacyPolicyPath + "/");
    }

    private boolean samePortalOrigin(Uri left, Uri right) {
        return equalsIgnoreCase(left.getScheme(), right.getScheme())
            && equalsIgnoreCase(normalizeHost(left.getHost()), normalizeHost(right.getHost()))
            && effectivePort(left) == effectivePort(right);
    }

    private String normalizeHost(String host) {
        if (host == null) {
            return null;
        }
        return host.regionMatches(true, 0, "www.", 0, 4) ? host.substring(4) : host;
    }

    private String studentPortalUrl() {
        return serverRoot + "/student/login";
    }

    private int effectivePort(Uri uri) {
        if (uri.getPort() >= 0) {
            return uri.getPort();
        }
        return "https".equalsIgnoreCase(uri.getScheme()) ? 443 : 80;
    }

    private boolean equalsIgnoreCase(String left, String right) {
        return left != null && right != null && left.equalsIgnoreCase(right);
    }

    private void openExternal(Uri uri) {
        String scheme = uri.getScheme() == null ? "" : uri.getScheme().toLowerCase(Locale.ROOT);
        if (!scheme.equals("http") && !scheme.equals("https") && !scheme.equals("mailto") && !scheme.equals("tel")) {
            Toast.makeText(this, "This link type is not supported.", Toast.LENGTH_SHORT).show();
            return;
        }
        try {
            startActivity(new Intent(Intent.ACTION_VIEW, uri));
        } catch (ActivityNotFoundException exception) {
            Toast.makeText(this, "No application can open this link.", Toast.LENGTH_SHORT).show();
        }
    }

    private boolean hasNetworkConnection() {
        ConnectivityManager manager = (ConnectivityManager) getSystemService(Context.CONNECTIVITY_SERVICE);
        Network network = manager.getActiveNetwork();
        if (network == null) {
            return false;
        }
        NetworkCapabilities capabilities = manager.getNetworkCapabilities(network);
        return capabilities != null && (capabilities.hasTransport(NetworkCapabilities.TRANSPORT_WIFI)
            || capabilities.hasTransport(NetworkCapabilities.TRANSPORT_CELLULAR)
            || capabilities.hasTransport(NetworkCapabilities.TRANSPORT_ETHERNET)
            || capabilities.hasTransport(NetworkCapabilities.TRANSPORT_VPN));
    }

    private void showError() {
        dismissSplash();
        mainFrameFailed = true;
        pageProgress.setVisibility(View.GONE);
        errorPanel.setVisibility(View.VISIBLE);
    }

    private void hideError() {
        mainFrameFailed = false;
        errorPanel.setVisibility(View.GONE);
    }

    private boolean isDebuggable() {
        return (getApplicationInfo().flags & ApplicationInfo.FLAG_DEBUGGABLE) != 0;
    }

    private int dp(int value) {
        return Math.round(value * getResources().getDisplayMetrics().density);
    }

    private void registerPredictiveBackCallback() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU) {
            return;
        }
        backInvokedCallback = this::handleBackNavigation;
        getOnBackInvokedDispatcher().registerOnBackInvokedCallback(
            OnBackInvokedDispatcher.PRIORITY_DEFAULT,
            backInvokedCallback
        );
    }

    private void handleBackNavigation() {
        if (webView.canGoBack()) {
            webView.goBack();
        } else {
            finishAfterTransition();
        }
    }

    @Override
    protected void onSaveInstanceState(Bundle outState) {
        Bundle webState = new Bundle();
        webView.saveState(webState);
        outState.putBundle(SAVED_WEBVIEW_STATE, webState);
        super.onSaveInstanceState(outState);
    }

    @Override
    protected void onPause() {
        // Account registration and new-device sign-in both send the student out
        // to their mail app in the middle of a Laravel session, and Android may
        // kill this process while it is in the background. Cookies are otherwise
        // only flushed on page load, so persist them now: losing the session
        // cookie here would drop a half-finished registration.
        CookieManager.getInstance().flush();
        super.onPause();
    }

    @Override
    @SuppressWarnings("deprecation")
    @SuppressLint("GestureBackNavigation")
    public void onBackPressed() {
        handleBackNavigation();
    }

    @Override
    protected void onActivityResult(int requestCode, int resultCode, Intent data) {
        super.onActivityResult(requestCode, resultCode, data);
        if (requestCode != FILE_CHOOSER_REQUEST || fileChooserCallback == null) {
            return;
        }
        Uri[] result = WebChromeClient.FileChooserParams.parseResult(resultCode, data);
        fileChooserCallback.onReceiveValue(result);
        fileChooserCallback = null;
    }

    @Override
    public void onRequestPermissionsResult(int requestCode, String[] permissions, int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);
        if (requestCode != STORAGE_PERMISSION_REQUEST || pendingDownload == null) {
            return;
        }
        PendingDownload download = pendingDownload;
        pendingDownload = null;
        if (grantResults.length > 0 && grantResults[0] == PackageManager.PERMISSION_GRANTED) {
            enqueueDownload(download);
        } else {
            Toast.makeText(this, "Storage permission is required to save this file.", Toast.LENGTH_LONG).show();
        }
    }

    @Override
    protected void onDestroy() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU && backInvokedCallback != null) {
            getOnBackInvokedDispatcher().unregisterOnBackInvokedCallback(backInvokedCallback);
            backInvokedCallback = null;
        }
        if (fileChooserCallback != null) {
            fileChooserCallback.onReceiveValue(null);
            fileChooserCallback = null;
        }
        if (webView != null) {
            webView.stopLoading();
            webView.setWebChromeClient(null);
            webView.setWebViewClient(null);
            webView.destroy();
        }
        super.onDestroy();
    }

    private final class StudentWebViewClient extends WebViewClient {
        @Override
        public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
            Uri destination = request.getUrl();
            if (isAllowedStudentUrl(destination)) {
                return false;
            }

            // First-party redirects (including the landing page and www host
            // alias) stay in the application and return to the student login.
            if (destination != null && samePortalOrigin(Uri.parse(serverRoot), destination)) {
                view.loadUrl(studentPortalUrl());
                return true;
            }

            openExternal(destination);
            return true;
        }

        @Override
        public void onPageStarted(WebView view, String url, android.graphics.Bitmap favicon) {
            mainFrameFailed = false;
            errorPanel.setVisibility(View.GONE);
            pageProgress.setVisibility(View.VISIBLE);
        }

        @Override
        public void onPageFinished(WebView view, String url) {
            CookieManager.getInstance().flush();
            pageProgress.setVisibility(View.GONE);
            if (!mainFrameFailed) {
                errorPanel.setVisibility(View.GONE);
            }
            dismissSplash();
        }

        @Override
        public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
            if (request.isForMainFrame()) {
                showError();
            }
        }

        @Override
        public void onReceivedSslError(WebView view, SslErrorHandler handler, android.net.http.SslError error) {
            handler.cancel();
            showError();
            Toast.makeText(MainActivity.this, "The server certificate could not be verified.", Toast.LENGTH_LONG).show();
        }

        @Override
        public boolean onRenderProcessGone(WebView view, RenderProcessGoneDetail detail) {
            ((ViewGroup) view.getParent()).removeView(view);
            view.destroy();
            Toast.makeText(MainActivity.this, "The portal was restarted after a rendering problem.", Toast.LENGTH_LONG).show();
            recreate();
            return true;
        }
    }

    private final class StudentWebChromeClient extends WebChromeClient {
        @Override
        public void onProgressChanged(WebView view, int newProgress) {
            pageProgress.setProgress(newProgress);
            pageProgress.setVisibility(newProgress >= 100 ? View.GONE : View.VISIBLE);
        }

        @Override
        public boolean onShowFileChooser(
            WebView view,
            ValueCallback<Uri[]> callback,
            FileChooserParams fileChooserParams
        ) {
            if (fileChooserCallback != null) {
                fileChooserCallback.onReceiveValue(null);
            }
            fileChooserCallback = callback;
            try {
                Intent chooser = fileChooserParams.createIntent();
                chooser.addCategory(Intent.CATEGORY_OPENABLE);
                startActivityForResult(chooser, FILE_CHOOSER_REQUEST);
                return true;
            } catch (ActivityNotFoundException exception) {
                fileChooserCallback = null;
                Toast.makeText(MainActivity.this, "No file picker is available.", Toast.LENGTH_LONG).show();
                return false;
            }
        }

        @Override
        public void onPermissionRequest(android.webkit.PermissionRequest request) {
            request.deny();
        }
    }

    private final class StudentDownloadListener implements DownloadListener {
        @Override
        public void onDownloadStart(
            String url,
            String userAgent,
            String contentDisposition,
            String mimeType,
            long contentLength
        ) {
            Uri uri = Uri.parse(url);
            if (!isAllowedStudentUrl(uri)) {
                openExternal(uri);
                return;
            }

            PendingDownload download = new PendingDownload(
                url,
                userAgent,
                contentDisposition,
                mimeType
            );

            if (Build.VERSION.SDK_INT <= Build.VERSION_CODES.P
                && checkSelfPermission(Manifest.permission.WRITE_EXTERNAL_STORAGE) != PackageManager.PERMISSION_GRANTED) {
                pendingDownload = download;
                requestPermissions(
                    new String[] {Manifest.permission.WRITE_EXTERNAL_STORAGE},
                    STORAGE_PERMISSION_REQUEST
                );
                return;
            }
            enqueueDownload(download);
        }
    }

    private void enqueueDownload(PendingDownload download) {
        try {
            String fileName = URLUtil.guessFileName(
                download.url,
                download.contentDisposition,
                download.mimeType
            ).replaceAll("[\\\\/:*?\"<>|]", "_");

            DownloadManager.Request request = new DownloadManager.Request(Uri.parse(download.url));
            request.setTitle(fileName);
            request.setDescription("MCC e-Clearance student document");
            request.setMimeType(download.mimeType);
            request.setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED);
            request.setAllowedOverMetered(true);
            request.setAllowedOverRoaming(false);
            request.addRequestHeader("User-Agent", download.userAgent);

            String cookies = CookieManager.getInstance().getCookie(download.url);
            if (cookies != null && !cookies.isBlank()) {
                request.addRequestHeader("Cookie", cookies);
            }

            request.setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, fileName);
            DownloadManager manager = (DownloadManager) getSystemService(Context.DOWNLOAD_SERVICE);
            manager.enqueue(request);
            Toast.makeText(this, "Downloading " + fileName, Toast.LENGTH_LONG).show();
        } catch (Exception exception) {
            Toast.makeText(this, "The document could not be downloaded.", Toast.LENGTH_LONG).show();
        }
    }

    private static final class PendingDownload {
        private final String url;
        private final String userAgent;
        private final String contentDisposition;
        private final String mimeType;

        private PendingDownload(String url, String userAgent, String contentDisposition, String mimeType) {
            this.url = url;
            this.userAgent = userAgent;
            this.contentDisposition = contentDisposition;
            this.mimeType = mimeType;
        }
    }
}
