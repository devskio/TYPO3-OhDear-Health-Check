# TYPO3 OhDear Health Check Extension

The TYPO3 OhDear Health Check Extension allows you to monitor the health and performance of your TYPO3 application and server using Oh Dear. With this extension, you can receive alerts and notifications for critical issues, ensuring the smooth operation of your TYPO3 application.

## Features

- Disk Space: Monitors the disk space usage of your server.
- PHP Error Log Size: Checks the size of the PHP error log.
- TYPO3 Error Log Size: Checks the size of the TYPO3 error log.
- MySQL Database Size: Checks the size of the MySQL database.
- Forgotten Files on the Server: Scans the document root for forgotten files.
- TYPO3 Database Log: Retrieves the TYPO3 database log.
- TYPO3 Version: Retrieves the installed TYPO3 version.

## Requirements

- TYPO3 version 11.5.0 or later.
- An active Oh Dear account with the necessary API credentials.

## Installation

1. Run the following command to require the OhDear Health Check Extension via Composer: `composer require devskio/typo3_ohdear_health_check`
2. Include the static TypoScript of the extension. `EXT:typo3_ohdear_health_check/Configuration/TypoScript/setup.typoscript`
3. In sites configuration yaml file, add routeEnhancers for OhDear Health Check Extension:
```
...
routeEnhancers:
  PageTypeSuffix:
    map:
      healthcheck: 1689678601
...
```
4. Once installed, go to the extension configuration settings and provide your Oh Dear API credentials.

## Usage

1. After installing and configuring the extension, you can access the OhDear Health Check dashboard.
2. The dashboard displays the current status of various monitored aspects, such as disk space, PHP Error Log Size, TYPO3 Error Log Size, MySQL Database Size, Forgotten Files on the Server, TYPO3 Database Log, TYPO3 Version.
3. Configure the desired alert thresholds and notification settings in OhDear.
4. When an issue is detected, you will receive alerts through your preferred communication channels (e.g., email, Slack, SMS) based on your Oh Dear configuration.

## Contributing

Contributions to the TYPO3 OhDear Health Check Extension are welcome! If you encounter any bugs, have suggestions, or want to contribute new features, please submit a pull request or open an issue in the GitHub repository.

## License

This TYPO3 OhDear Health Check Extension is released under the [MIT License](LICENSE).

