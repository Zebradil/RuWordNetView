# syntax=docker/dockerfile:1@sha256:87999aa3d42bdc6bea60565083ee17e86d1f3339802f543c0d03998580f9cb89

FROM golang:1.26-bookworm@sha256:5d2b868674b57c9e48cdd39e891acce4196b6926ca6d11e9c270a8f85106203d AS build
WORKDIR /src
COPY go.mod go.sum ./
RUN go mod download
COPY . .
RUN CGO_ENABLED=0 GOOS=linux go build -trimpath -ldflags="-s -w" \
    -o /out/ruwordnetview ./cmd/ruwordnetview

FROM gcr.io/distroless/static-debian12:nonroot@sha256:d093aa3e30dbadd3efe1310db061a14da60299baff8450a17fe0ccc514a16639
WORKDIR /app
COPY --from=build /out/ruwordnetview ./ruwordnetview
COPY --from=build /src/views ./views
COPY --from=build /src/app/locales ./app/locales
COPY --from=build /src/web ./web
USER nonroot:nonroot
EXPOSE 8000
ENTRYPOINT ["/app/ruwordnetview"]
