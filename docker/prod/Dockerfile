# syntax=docker/dockerfile:1@sha256:4edf897a3ffa55b89f906fc8cc78afdb3f1834cc9c7083565e611a8a7d5fe99e

FROM golang:1.27-bookworm@sha256:c93aa68d51e382ec1ab4caa559ae9efd7858d8e5330051671c56c1096eaccd66 AS build
WORKDIR /src
COPY go.mod go.sum ./
RUN go mod download
COPY . .
RUN CGO_ENABLED=0 GOOS=linux go build -trimpath -ldflags="-s -w" \
    -o /out/ruwordnetview ./cmd/ruwordnetview

FROM gcr.io/distroless/static-debian12:nonroot@sha256:afa5c872c891853ca7fcf1f12c3edb23f7eeef36189728842dd51042ff57f7ab
WORKDIR /app
COPY --from=build /out/ruwordnetview ./ruwordnetview
COPY --from=build /src/views ./views
COPY --from=build /src/app/locales ./app/locales
COPY --from=build /src/web ./web
USER nonroot:nonroot
EXPOSE 8000
ENTRYPOINT ["/app/ruwordnetview"]
