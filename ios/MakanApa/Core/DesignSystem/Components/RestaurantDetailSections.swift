import PhotosUI
import SwiftUI

/// Shared between Nearby's marker sheet and `CommunityRestaurantDetailSheet` — both render the
/// same `PlaceDetails` payload, so these stay as one implementation rather than drifting apart
/// as Community Places grows richer.

/// One-tap "add a photo" for a restaurant that has no Google photo and no community photo —
/// closes the gap Google's Places API leaves on places it has no licensed photo for. Shared
/// upload state machine behind two presentations (`QuickAddPhotoTile`, `QuickAddPhotoRow`) so
/// Nearby's square photo slot and Community's compact sheet don't duplicate the upload logic.
@MainActor
@Observable
private final class QuickAddPhotoUploader {
    enum State: Equatable { case idle, uploading, done, failed }

    var state: State = .idle

    func upload(_ item: PhotosPickerItem, restaurantId: Int) async {
        state = .uploading
        guard let data = try? await item.loadTransferable(type: Data.self), let image = UIImage(data: data) else {
            state = .failed
            return
        }
        let resized = image.resizedIfNeeded(maxDimension: 1600)
        guard let jpegData = resized.jpegData(compressionQuality: 0.85) else {
            state = .failed
            return
        }
        do {
            _ = try await APIClient.quickAddRestaurantPhoto(restaurantId: restaurantId, jpegData: jpegData, photoType: "other")
            state = .done
        } catch {
            state = .failed
        }
    }
}

/// Square tile variant — drops into Nearby's existing photo frame in place of the plain
/// fork/knife placeholder.
struct QuickAddPhotoTile: View {
    let restaurantId: Int

    @State private var uploader = QuickAddPhotoUploader()
    @State private var pickerItem: PhotosPickerItem?

    var body: some View {
        PhotosPicker(selection: $pickerItem, matching: .images) {
            VStack(spacing: 6) {
                switch uploader.state {
                case .idle:
                    Image(systemName: "camera.fill").font(.system(size: 28))
                    Text("Add a photo").font(.makanBody(12))
                case .uploading:
                    ProgressView()
                case .done:
                    Image(systemName: "checkmark.circle.fill").font(.system(size: 28)).foregroundStyle(Color.pandan)
                    Text("Thanks! Pending review").font(.makanBody(11)).foregroundStyle(.secondary)
                case .failed:
                    Image(systemName: "camera.fill").font(.system(size: 28))
                    Text("Couldn't add — tap to retry").font(.makanBody(12))
                }
            }
            .foregroundStyle(uploader.state == .done ? Color.kicap : Color.kunyit)
        }
        .disabled(uploader.state == .uploading || uploader.state == .done)
        .onChange(of: pickerItem) { _, newItem in
            guard let newItem else { return }
            Task {
                await uploader.upload(newItem, restaurantId: restaurantId)
                pickerItem = nil
            }
        }
    }
}

/// Compact row variant — used where there's no big photo hero to begin with (Community's
/// detail sheet only ever shows a photo section when one exists).
struct QuickAddPhotoRow: View {
    let restaurantId: Int

    @State private var uploader = QuickAddPhotoUploader()
    @State private var pickerItem: PhotosPickerItem?

    var body: some View {
        PhotosPicker(selection: $pickerItem, matching: .images) {
            HStack(spacing: 8) {
                switch uploader.state {
                case .idle:
                    Image(systemName: "camera.fill")
                    Text("No photos yet — add one?")
                case .uploading:
                    ProgressView()
                    Text("Uploading…")
                case .done:
                    Image(systemName: "checkmark.circle.fill").foregroundStyle(Color.pandan)
                    Text("Thanks! Pending review")
                case .failed:
                    Image(systemName: "camera.fill")
                    Text("Couldn't add — tap to retry")
                }
            }
            .font(.makanBody(13))
            .foregroundStyle(uploader.state == .done ? .secondary : Color.sambalRed)
        }
        .disabled(uploader.state == .uploading || uploader.state == .done)
        .onChange(of: pickerItem) { _, newItem in
            guard let newItem else { return }
            Task {
                await uploader.upload(newItem, restaurantId: restaurantId)
                pickerItem = nil
            }
        }
    }
}

/// Horizontal strip of community-contributed photos, captioned once rather than per-photo.
struct PlaceCommunityPhotoStrip: View {
    let urls: [String]

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            ScrollView(.horizontal, showsIndicators: false) {
                HStack(spacing: 10) {
                    ForEach(urls, id: \.self) { urlString in
                        // Community/S3 photos only (never Google) — safe to cache in memory.
                        // Keyed by URL since these aren't currently exposed with a stable photo
                        // ID to the client; a good follow-up, not blocking.
                        RemoteImage(
                            url: URL(string: urlString),
                            cachePolicy: .memory(key: urlString),
                            targetSize: CGSize(width: 140, height: 140)
                        ) {
                            Color.kicap.opacity(0.06)
                        }
                        .aspectRatio(contentMode: .fill)
                        .frame(width: 140, height: 140)
                        .clipShape(RoundedRectangle(cornerRadius: 14))
                        .transition(.opacity)
                    }
                }
            }
            Text("Shared by the MakanApa community")
                .font(.makanBody(11))
                .foregroundStyle(.secondary)
        }
    }
}

struct PlaceMenuSection: View {
    let items: [MenuItem]

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            ForEach(items) { item in
                HStack {
                    Text(item.name).font(.makanBody(14)).foregroundStyle(Color.kicap)
                    Spacer()
                    if let price = item.price {
                        Text("RM\(price, specifier: "%.2f")").font(.makanBody(13)).foregroundStyle(.secondary)
                    }
                }
            }
        }
    }
}
