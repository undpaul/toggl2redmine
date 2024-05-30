FROM composer:2.7
RUN composer --version
RUN composer req undpaul/toggl2redmine
WORKDIR /app/vendor/undpaul/toggl2redmine
ENTRYPOINT ["./toggl2redmine"]
